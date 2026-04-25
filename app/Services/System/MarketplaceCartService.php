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
    public function add(string $slug, int $quantity = 1): ?array
    {
        $quantity = max(1, min(self::MAX_QTY, $quantity));

        $listing = MarketplaceListing::published()->where('slug', $slug)->first();
        if (!$listing) {
            return null;
        }

        $cart = $this->get();
        $key  = (string) $listing->id;

        if (isset($cart['items'][$key])) {
            $newQty = min(self::MAX_QTY, (int) $cart['items'][$key]['quantity'] + $quantity);
            $newQty = min($newQty, max(1, (int) $listing->stock));
            $cart['items'][$key]['quantity'] = $newQty;
            // refrescamos snapshot a precio actual también, evita que el cart
            // tenga precio rancio si se aumentó cantidad horas después
            $cart['items'][$key]['price'] = (float) $listing->display_price;
        } else {
            $cart['items'][$key] = [
                'listing_id'     => (int) $listing->id,
                'slug'           => $listing->slug,
                'title'          => $listing->title,
                'image_url'      => $listing->image_url,
                'price'          => (float) $listing->display_price,
                'quantity'       => min($quantity, max(1, (int) $listing->stock)),
                'hostname_id'    => (int) $listing->hostname_id,
                'tenant_fqdn'    => $listing->tenant_fqdn,
                'tenant_name'    => $listing->seller_display,
                'remote_item_id' => (int) $listing->remote_item_id,
                'tenant_subdomain' => $listing->subdomain,
                'tenant_logo_url'  => $listing->tenant_logo_url,
            ];
        }

        $cart['updated_at'] = time();
        Session::put(self::SESSION_KEY, $cart);

        return $cart['items'][$key];
    }

    /**
     * Actualiza la cantidad de un item específico. quantity=0 elimina.
     */
    public function setQuantity(int $listingId, int $quantity): bool
    {
        $cart = $this->get();
        $key  = (string) $listingId;

        if (!isset($cart['items'][$key])) {
            return false;
        }

        if ($quantity <= 0) {
            unset($cart['items'][$key]);
        } else {
            $listing = MarketplaceListing::find($listingId);
            $maxAllowed = $listing ? max(1, (int) $listing->stock) : self::MAX_QTY;
            $cart['items'][$key]['quantity'] = min(self::MAX_QTY, max(1, $quantity), $maxAllowed);
        }

        $cart['updated_at'] = time();
        Session::put(self::SESSION_KEY, $cart);

        return true;
    }

    public function remove(int $listingId): bool
    {
        return $this->setQuantity($listingId, 0);
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

        foreach ($cart['items'] as $key => $line) {
            $listing = $listings->get($line['listing_id']);
            if (!$listing || !$listing->is_active || $listing->status !== 'active' || $listing->stock <= 0) {
                unset($cart['items'][$key]);
                continue;
            }
            $cart['items'][$key]['price']     = (float) $listing->display_price;
            $cart['items'][$key]['title']     = $listing->title;
            $cart['items'][$key]['image_url'] = $listing->image_url;
            $cart['items'][$key]['quantity']  = min(
                self::MAX_QTY,
                max(1, (int) $line['quantity']),
                max(1, (int) $listing->stock)
            );
            $cart['items'][$key]['tenant_name']     = $listing->seller_display;
            $cart['items'][$key]['tenant_fqdn']     = $listing->tenant_fqdn;
            $cart['items'][$key]['tenant_subdomain'] = $listing->subdomain;
            $cart['items'][$key]['tenant_logo_url'] = $listing->tenant_logo_url;
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
     * Valida stock contra el snapshot actual de listings antes de checkout.
     * Devuelve lista de errores; vacío = OK para proceder.
     */
    public function validateStock(): array
    {
        $errors = [];
        $cart = $this->get();
        if (empty($cart['items'])) {
            $errors[] = 'Tu carrito está vacío.';
            return $errors;
        }

        $ids = array_column($cart['items'], 'listing_id');
        $listings = MarketplaceListing::query()->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($cart['items'] as $line) {
            $listing = $listings->get($line['listing_id']);
            if (!$listing || !$listing->is_active || $listing->status !== 'active') {
                $errors[] = "El producto '{$line['title']}' ya no está disponible.";
                continue;
            }
            if ((int) $listing->stock < (int) $line['quantity']) {
                $errors[] = "Stock insuficiente para '{$line['title']}': quedan {$listing->stock}.";
            }
        }

        return $errors;
    }
}
