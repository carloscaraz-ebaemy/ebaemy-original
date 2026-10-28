<?php

namespace App\Services\System;

use App\Models\System\MarketplaceListing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

/**
 * Carrito del marketplace central. Persiste en la sesión del comprador
 * (cookie de Laravel firmada). Lo consume MarketplaceCartController para
 * los endpoints REST y la vista, y MarketplaceCheckoutService al confirmar.
 *
 * Estructura en sesión (key: 'mp_cart'):
 *   [
 *     'items' => [
 *       (string) listing_id => [
 *         'listing_id', 'slug', 'title', 'image_url',
 *         'price' (snapshot al añadir),
 *         'quantity',
 *         'hostname_id', 'tenant_fqdn', 'tenant_name',
 *       ],
 *       ...
 *     ],
 *     'updated_at' => unix_ts,
 *   ]
 *
 * Diseño consciente:
 *   - Snapshot del precio al añadir (refresh() lo re-sincroniza si cambió)
 *   - quantity 0 elimina el item (idempotente con remove)
 *   - max 99 por línea (defensivo contra typos)
 *   - validateStock() bloquea cantidades > stock al checkout
 */
class MarketplaceCartService
{
    private const SESSION_KEY = 'mp_cart';
    private const MAX_QTY     = 99;

    /**
     * Devuelve el carrito desde sesión, garantizando estructura mínima.
     */
    public function get(): array
    {
        $cart = Session::get(self::SESSION_KEY, ['items' => [], 'updated_at' => null]);
        if (!is_array($cart) || !isset($cart['items']) || !is_array($cart['items'])) {
            $cart = ['items' => [], 'updated_at' => null];
        }
        return $cart;
    }

    /**
     * Añade un listing al carrito (incrementando cantidad si ya estaba).
     * Devuelve el item resultante o null si el listing no es publicable.
     */
    public function add(string $slug, int $quantity = 1, ?int $tenantVariantId = null): ?array
    {
        $quantity = max(1, min(self::MAX_QTY, $quantity));

        $listing = MarketplaceListing::published()->where('slug', $slug)->first();
        if (!$listing) {
            return null;
        }

        // Variante: la ficha manda el tenant_variant_id de la talla/color
        // elegida. Sin ella (agregado rápido desde una card) se usa la
        // principal con stock, como la que la ficha muestra preseleccionada.
        $variant = null;
        if ($listing->has_variants) {
            $variant = $this->resolveVariant($listing, $tenantVariantId);
            if (!$variant) {
                return null;
            }
        }

        $stock = (int) ($variant ? $variant->stock : $listing->stock);
        $price = $this->linePrice($listing, $variant);

        $cart = $this->get();
        $key  = self::lineKey($listing->id, $variant?->id);

        if (isset($cart['items'][$key])) {
            $newQty = min(self::MAX_QTY, (int) $cart['items'][$key]['quantity'] + $quantity);
            $newQty = min($newQty, max(1, $stock));
            $cart['items'][$key]['quantity'] = $newQty;
            // refrescamos snapshot a precio actual también, evita que el cart
            // tenga precio rancio si se aumentó cantidad horas después
            $cart['items'][$key]['price'] = $price;
        } else {
            $cart['items'][$key] = [
                'line_key'       => $key,
                'listing_id'     => (int) $listing->id,
                'listing_variant_id' => $variant?->id,
                'tenant_variant_id'  => $variant ? (int) $variant->tenant_variant_id : null,
                'variant_name'   => $variant?->display_name,
                'slug'           => $listing->slug,
                'title'          => $listing->title,
                'image_url'      => ($variant?->image_url) ?: $listing->image_url,
                'price'          => $price,
                'quantity'       => min($quantity, max(1, $stock)),
                'hostname_id'    => (int) $listing->hostname_id,
                'tenant_fqdn'    => $listing->tenant_fqdn,
                'tenant_name'    => $listing->seller_display,
                'remote_item_id' => (int) $listing->remote_item_id,
                'tenant_subdomain' => $listing->subdomain,
                'tenant_logo_url'  => $listing->tenant_logo_url,
                // Política de envío de la tienda (null = sin confirmar).
                'shipping'         => is_array($listing->shipping ?? null) ? $listing->shipping : null,
                // Marca pack para mostrar badge "📦 Pack ×N" en el cart y
                // recordarle al cliente que está comprando un combo.
                'is_pack'        => (bool) ($listing->is_pack ?? false),
                'pack_count'     => is_array($listing->pack_contents ?? null)
                                        ? count($listing->pack_contents) : 0,
            ];
        }

        $cart['updated_at'] = time();
        Session::put(self::SESSION_KEY, $cart);

        return $cart['items'][$key];
    }

    /** Clave de la línea: "{listing}" o "{listing}-{variante}" (dos tallas = dos líneas). */
    public static function lineKey(int $listingId, ?int $listingVariantId = null): string
    {
        return $listingVariantId ? $listingId . '-' . $listingVariantId : (string) $listingId;
    }

    /** Variante activa con stock: la pedida o, si no se indicó, la principal. */
    private function resolveVariant(MarketplaceListing $listing, ?int $tenantVariantId): ?\App\Models\System\MarketplaceListingVariant
    {
        $query = $listing->variants()->where('is_active', true)->where('stock', '>', 0);

        if ($tenantVariantId) {
            return (clone $query)->where('tenant_variant_id', $tenantVariantId)->first();
        }

        return $query->orderByDesc('is_primary')->orderBy('id')->first();
    }

    /** Precio de la línea: el de la variante si lo tiene (es el que la ficha mostró). */
    private function linePrice(MarketplaceListing $listing, $variant): float
    {
        return $variant && (float) $variant->price > 0 ? (float) $variant->price : (float) $listing->display_price;
    }

    /**
     * Actualiza la cantidad de un item específico. quantity=0 elimina.
     * $key es la clave de línea (ver lineKey); un id de listing a secas sigue
     * sirviendo para las líneas sin variante.
     */
    public function setQuantity(string $key, int $quantity): bool
    {
        $cart = $this->get();

        if (!isset($cart['items'][$key])) {
            return false;
        }

        if ($quantity <= 0) {
            unset($cart['items'][$key]);
        } else {
            $line = $cart['items'][$key];
            $source = !empty($line['listing_variant_id'])
                ? \App\Models\System\MarketplaceListingVariant::find($line['listing_variant_id'])
                : MarketplaceListing::find($line['listing_id']);
            $maxAllowed = $source ? max(1, (int) $source->stock) : self::MAX_QTY;
            $cart['items'][$key]['quantity'] = min(self::MAX_QTY, max(1, $quantity), $maxAllowed);
        }

        $cart['updated_at'] = time();
        Session::put(self::SESSION_KEY, $cart);

        // Si removimos el último item de una tienda, su cupón ya no aplica.
        $this->pruneOrphanCoupons();

        return true;
    }

    public function remove(string $key): bool
    {
        return $this->setQuantity($key, 0);
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Re-sincroniza precio/stock/título contra los listings actuales.
     * Elimina items cuyo listing ya no esté publicable.
     */
    public function refresh(): array
    {
        $cart = $this->get();
        if (empty($cart['items'])) {
            return $cart;
        }

        $ids = array_column($cart['items'], 'listing_id');
        $listings = MarketplaceListing::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $variants = \App\Models\System\MarketplaceListingVariant::query()
            ->whereIn('id', array_filter(array_column($cart['items'], 'listing_variant_id')) ?: [0])
            ->get()
            ->keyBy('id');

        foreach ($cart['items'] as $key => $line) {
            $listing = $listings->get($line['listing_id']);
            $variant = !empty($line['listing_variant_id']) ? $variants->get($line['listing_variant_id']) : null;
            $stock = $variant ? (int) $variant->stock : (int) ($listing->stock ?? 0);
            if (!$listing || !$listing->is_active || $listing->status !== 'active' || $listing->stock <= 0
                || (!empty($line['listing_variant_id']) && (!$variant || !$variant->is_active || $stock <= 0))) {
                unset($cart['items'][$key]);
                continue;
            }
            $cart['items'][$key]['price']     = $this->linePrice($listing, $variant);
            $cart['items'][$key]['title']     = $listing->title;
            $cart['items'][$key]['image_url'] = ($variant?->image_url) ?: $listing->image_url;
            $cart['items'][$key]['line_key']  = (string) $key;
            $cart['items'][$key]['quantity']  = min(
                self::MAX_QTY,
                max(1, (int) $line['quantity']),
                max(1, $stock)
            );
            $cart['items'][$key]['tenant_name']     = $listing->seller_display;
            $cart['items'][$key]['tenant_fqdn']     = $listing->tenant_fqdn;
            $cart['items'][$key]['tenant_subdomain'] = $listing->subdomain;
            $cart['items'][$key]['tenant_logo_url'] = $listing->tenant_logo_url;
            $cart['items'][$key]['shipping']        = is_array($listing->shipping ?? null) ? $listing->shipping : null;
        }

        $cart['updated_at'] = time();
        Session::put(self::SESSION_KEY, $cart);

        return $cart;
    }

    /**
     * Vista agrupada por tienda. Retorna estructura lista para la vista:
     *   [
     *     ['hostname_id', 'tenant_name', 'tenant_subdomain', 'tenant_fqdn',
     *      'tenant_logo_url', 'items' => [...], 'subtotal' => float, 'item_count' => int],
     *     ...
     *   ]
     */
    public function groupedByStore(): Collection
    {
        $cart = $this->get();

        return collect($cart['items'])
            ->groupBy('hostname_id')
            ->map(function (Collection $lines, $hostnameId) {
                $first = $lines->first();
                $subtotal = $lines->sum(fn ($l) => (float) $l['price'] * (int) $l['quantity']);
                $itemCount = $lines->sum('quantity');

                return [
                    'hostname_id'      => (int) $hostnameId,
                    'tenant_name'      => $first['tenant_name'] ?? null,
                    'tenant_fqdn'      => $first['tenant_fqdn'] ?? null,
                    'tenant_subdomain' => $first['tenant_subdomain'] ?? null,
                    'tenant_logo_url'  => $first['tenant_logo_url'] ?? null,
                    'shipping'         => $first['shipping'] ?? null,
                    'items'            => $lines->values()->all(),
                    'subtotal'         => round($subtotal, 2),
                    'item_count'       => (int) $itemCount,
                ];
            })
            ->values();
    }

    /**
     * Resumen rápido: total de unidades + total monetario. Para badge nav y
     * footer del cart drawer.
     */
    public function summary(): array
    {
        $cart = $this->get();
        $count = 0;
        $total = 0.0;
        foreach ($cart['items'] as $line) {
            $qty = (int) $line['quantity'];
            $count += $qty;
            $total += $qty * (float) $line['price'];
        }
        return [
            'count'    => $count,
            'lines'    => count($cart['items']),
            'subtotal' => round($total, 2),
            'total'    => round($total, 2),
        ];
    }

    /**
     * Detalle compacto para el mini-cart drawer del navbar. Igual que
     * summary() pero también devuelve los items agrupados por tienda
     * (lightweight — solo lo necesario para mostrar el panel).
     */
    public function miniDetails(): array
    {
        $stores = $this->groupedByStore();
        $miniStores = $stores->map(function ($store) {
            return [
                'hostname_id'  => $store['hostname_id'],
                'tenant_fqdn'  => $store['tenant_fqdn'] ?? null,
                'tenant_name'  => $store['tenant_name'] ?? '',
                'tenant_logo'  => $store['tenant_logo'] ?? null,
                'subtotal'     => $store['subtotal'] ?? 0,
                'items'        => collect($store['items'] ?? [])
                    ->map(fn ($l) => [
                        'listing_id'  => $l['listing_id']  ?? null,
                        'line_key'    => $l['line_key']    ?? (string) ($l['listing_id'] ?? ''),
                        'slug'        => $l['slug']        ?? null,
                        'title'       => ($l['title'] ?? '') . (!empty($l['variant_name']) ? ' · ' . $l['variant_name'] : ''),
                        'image'       => $l['image_url']   ?? null,
                        'quantity'    => (int) ($l['quantity'] ?? 0),
                        'price'       => (float) ($l['price'] ?? 0),
                        'line_total'  => round(
                            (int) ($l['quantity'] ?? 0) * (float) ($l['price'] ?? 0),
                            2
                        ),
                    ])
                    ->values(),
            ];
        })->values();

        return [
            'stores'  => $miniStores,
            'summary' => $this->summary(),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Cupones aplicados por tienda — persistencia en sesión
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Devuelve los cupones aplicados, mapeados por hostname_id.
     * Estructura: { 123 => ['code' => 'EBA10', 'discount' => 12.5], ... }
     */
    public function getCoupons(): array
    {
        $cart = $this->get();
        return is_array($cart['coupons'] ?? null) ? $cart['coupons'] : [];
    }

    /**
     * Persistir un cupón aplicado a una tienda. El descuento se guarda como
     * snapshot — al pagar se re-valida server-side por MarketplaceCheckoutService.
     */
    public function applyCoupon(int $hostnameId, string $code, float $discount): void
    {
        $cart = $this->get();
        $coupons = is_array($cart['coupons'] ?? null) ? $cart['coupons'] : [];
        $coupons[$hostnameId] = [
            'code'     => strtoupper($code),
            'discount' => round($discount, 2),
            'applied_at' => time(),
        ];
        $cart['coupons'] = $coupons;
        $cart['updated_at'] = time();
        Session::put(self::SESSION_KEY, $cart);
    }

    /**
     * Quitar un cupón de una tienda (cliente clickeó "Cambiar" o se removió
     * todos los items de esa tienda y ya no aplica).
     */
    public function removeCoupon(int $hostnameId): void
    {
        $cart = $this->get();
        if (isset($cart['coupons'][$hostnameId])) {
            unset($cart['coupons'][$hostnameId]);
            Session::put(self::SESSION_KEY, $cart);
        }
    }

    /**
     * Limpia cupones de tiendas que ya no están en el cart (el cliente quitó
     * todos sus items). Llamar después de remove/clear/update para no quedar
     * con cupones huérfanos en sesión.
     */
    public function pruneOrphanCoupons(): void
    {
        $cart = $this->get();
        if (empty($cart['coupons'])) return;

        $activeHostnameIds = collect($cart['items'])->pluck('hostname_id')->unique()->all();
        $coupons = [];
        foreach (($cart['coupons'] ?? []) as $hostId => $data) {
            if (in_array((int) $hostId, array_map('intval', $activeHostnameIds), true)) {
                $coupons[$hostId] = $data;
            }
        }
        $cart['coupons'] = $coupons;
        Session::put(self::SESSION_KEY, $cart);
    }

    /**
     * Valida stock contra el snapshot actual de listings antes de checkout.
     * Devuelve lista de errores; vacío = OK para proceder.
     */
    public function validateStock(): array
    {
        return $this->doValidateStock(false);
    }

    /**
     * Misma validacion pero con SELECT ... FOR UPDATE para serializar
     * compradores concurrentes. Solo invocar DENTRO de una transaction —
     * los locks se liberan al commit/rollback.
     *
     * Reduce la ventana de race entre validar y crear el pedido: dos
     * compradores no pueden ambos ver "stock=1" simultaneamente y los
     * dos crear pedido por 1. El segundo ve el stock ya decrementado
     * (cuando exista decremento en system) o al menos serializa el
     * acceso para que el dispatcher al tenant detecte el conflicto
     * antes de cargar al cliente.
     */
    public function validateStockWithLock(): array
    {
        return $this->doValidateStock(true);
    }

    private function doValidateStock(bool $lock): array
    {
        $errors = [];
        $cart = $this->get();
        if (empty($cart['items'])) {
            $errors[] = 'Tu carrito está vacío.';
            return $errors;
        }

        $ids = array_column($cart['items'], 'listing_id');
        $query = MarketplaceListing::query()->whereIn('id', $ids);
        if ($lock) $query->lockForUpdate();
        $listings = $query->get()->keyBy('id');

        $variantQuery = \App\Models\System\MarketplaceListingVariant::query()
            ->whereIn('id', array_filter(array_column($cart['items'], 'listing_variant_id')) ?: [0]);
        if ($lock) $variantQuery->lockForUpdate();
        $variants = $variantQuery->get()->keyBy('id');

        foreach ($cart['items'] as $line) {
            $listing = $listings->get($line['listing_id']);
            if (!$listing || !$listing->is_active || $listing->status !== 'active') {
                $errors[] = "El producto '{$line['title']}' ya no está disponible.";
                continue;
            }

            // Línea con variante: el stock que cuenta es el de esa talla/color.
            if (!empty($line['listing_variant_id'])) {
                $variant = $variants->get($line['listing_variant_id']);
                $name = trim($line['title'] . ' - ' . ($line['variant_name'] ?? ''), ' -');
                if (!$variant || !$variant->is_active || (int) $variant->listing_id !== (int) $listing->id) {
                    $errors[] = "La opción '{$name}' ya no está disponible.";
                } elseif ((int) $variant->stock < (int) $line['quantity']) {
                    $errors[] = "Stock insuficiente para '{$name}': quedan {$variant->stock}.";
                }
                continue;
            }
            // Packs: el stock real es min(componente.stock / qty_en_pack), que
            // ya está precomputado en pack_stock por MarketplaceListingSyncService.
            // listing.stock para packs equivale a pack_stock — el chequeo abajo
            // basta, pero el mensaje queda más claro si distinguimos.
            $available = (int) $listing->stock;
            if ($available < (int) $line['quantity']) {
                if (!empty($listing->is_pack)) {
                    $errors[] = $available === 0
                        ? "El pack '{$line['title']}' no se puede armar ahora: falta stock de algún componente."
                        : "Solo se pueden armar {$available} pack(s) de '{$line['title']}' con el stock actual de los componentes.";
                } else {
                    $errors[] = "Stock insuficiente para '{$line['title']}': quedan {$available}.";
                }
            }
        }

        return $errors;
    }
}
