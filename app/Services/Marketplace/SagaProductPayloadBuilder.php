<?php

namespace App\Services\Marketplace;

use App\Models\Tenant\MarketplaceChannel;
use App\Models\Tenant\MarketplaceProduct;
use App\Models\Tenant\SagaCategoryMap;
use App\Models\Tenant\SagaCategoryAttribute;
use App\Models\Tenant\SagaBrandMap;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Support\Str;

/**
 * Construye el payload de ProductCreate/ProductUpdate para Saga Falabella a
 * partir de un mapeo interno (MarketplaceProduct → Item/Variant), y valida que
 * el producto tenga lo OBLIGATORIO antes de enviarlo (Fase 2).
 *
 * Formato: el documentado por Seller Center (Lazada-derivado) con bloque
 * <Attributes> a nivel producto + <Skus><Sku> a nivel SKU. La estructura exacta
 * de etiquetas se valida en vivo contra la cuenta en el primer push real (Fase 3),
 * pero esta es la forma canónica de la API de Falabella.
 *
 * @see https://developers.falabella.com/  (ProductCreate / GetCategoryAttributes)
 */
class SagaProductPayloadBuilder
{
    protected MarketplaceChannel $channel;
    protected MarketplaceProduct $mapping;
    protected $item;
    protected $variant;
    protected ?string $fqdn = null;

    /**
     * Atributos estándar que ESTE builder ya provee (normalizados sin
     * espacios/underscores, en minúsculas). Sirve para no marcar como "faltante"
     * un atributo obligatorio de la categoría que en realidad ya enviamos.
     */
    protected array $providedAttrKeys = [
        'name', 'productname', 'title',
        'brand', 'marca',
        'description', 'shortdescription', 'longdescription', 'descripcion',
        'packageweight', 'packagelength', 'packagewidth', 'packageheight',
        'price', 'precio', 'saleprice', 'specialprice',
        'quantity', 'stock', 'sellersku', 'sku', 'primarycategory',
        'productid', 'ean', 'barcode',
    ];

    public function __construct(MarketplaceChannel $channel, MarketplaceProduct $mapping)
    {
        $this->channel = $channel;
        $this->mapping = $mapping;
        $this->item = $mapping->item;
        $this->variant = $mapping->variant;
    }

    // ── Validación previa ───────────────────────────────────────

    /**
     * Lista de problemas que IMPIDEN publicar. Vacía = listo para enviar.
     */
    public function validate(): array
    {
        $errors = [];

        if (!$this->item) {
            return ['El producto interno no existe (item eliminado).'];
        }

        if ($this->sku() === '') {
            $errors[] = 'Sin SKU (define el código interno o el SKU externo).';
        }

        if (trim((string) $this->name()) === '') {
            $errors[] = 'Sin nombre/descripción del producto.';
        }

        if (!$this->sagaCategoryId()) {
            $catName = $this->item->category->name ?? ('#' . $this->item->category_id);
            $errors[] = "Categoría \"{$catName}\" sin homologar a Saga (ve a Marketplace → Categorías).";
        }

        if ($this->price() <= 0) {
            $errors[] = 'Precio inválido (debe ser mayor a 0).';
        }

        if (empty($this->images())) {
            $errors[] = 'Sin imágenes (Saga exige al menos 1 imagen con URL pública).';
        }

        if (($this->item->weight ?? 0) <= 0) {
            $errors[] = 'Falta el peso del paquete (kg).';
        }

        // Atributos obligatorios de la categoría que NO cubrimos con los campos
        // estándar (ej. Color, Talla, Material). Bloquean hasta tener un editor
        // de atributos por producto (fase futura).
        foreach ($this->missingMandatoryAttributes() as $label) {
            $errors[] = "Falta atributo obligatorio de la categoría: {$label}.";
        }

        return $errors;
    }

    // ── Resolución de campos ────────────────────────────────────

    public function sku(): string
    {
        return (string) (
            $this->mapping->external_sku
            ?: ($this->variant->sku ?? null)
            ?: ($this->item->internal_id ?? null)
            ?: ($this->item ? 'ITEM-' . $this->item->id : '')
        );
    }

    public function name(): string
    {
        // En EBAEMY `description` es el nombre visible del producto.
        $base = $this->item->description ?: $this->item->name;
        if ($this->variant && $this->variant->display_name) {
            $base = trim($base . ' ' . $this->variant->display_name);
        }
        return Str::limit(trim((string) $base), 255, '');
    }

    public function description(): string
    {
        $desc = $this->item->name && $this->item->name !== $this->item->description
            ? $this->item->name
            : $this->item->description;
        return (string) ($desc ?: $this->name());
    }

    public function shortDescription(): string
    {
        return Str::limit(strip_tags((string) $this->description()), 200, '');
    }

    public function brand(): string
    {
        // Homologación: si la marca interna está mapeada a una marca de Saga,
        // usamos el nombre EXACTO de Saga (su catálogo valida la marca). Si no,
        // caemos al nombre interno (Saga puede rechazar marca desconocida).
        $brandId = $this->item->brand_id ?? null;
        if ($brandId) {
            try {
                $mapped = SagaBrandMap::where('channel_id', $this->channel->id)
                    ->where('brand_id', $brandId)
                    ->value('saga_brand_name');
                if ($mapped) {
                    return (string) $mapped;
                }
            } catch (\Throwable $e) {
                // Tabla saga_brand_map aún no migrada (deploy de código antes que
                // la migración): degradamos al nombre interno en vez de romper el
                // push. Una vez aplicada la migración, la homologación funciona.
            }
        }
        return (string) ($this->item->brand->name ?? 'Genérica');
    }

    /**
     * Valores de atributos capturados por producto (editor), filtrando vacíos.
     * Mapa { nombre_atributo_saga: valor }.
     */
    public function storedAttributes(): array
    {
        $attrs = $this->mapping->attributes ?? [];
        if (!is_array($attrs)) {
            return [];
        }
        return array_filter($attrs, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    public function sagaCategoryId(): ?string
    {
        if (!$this->item->category_id) {
            return null;
        }
        return SagaCategoryMap::where('channel_id', $this->channel->id)
            ->where('category_id', $this->item->category_id)
            ->value('saga_category_id');
    }

    public function quantity(): int
    {
        if ($this->variant) {
            return max(0, (int) $this->variant->stock);
        }
        // Stock real = SUMA de item_warehouse.stock, igual que el marketplace
        // central (MarketplaceListingSyncService). NO usamos items.stock porque
        // puede quedar desincronizado (sistema dual stock/stock_physical). Las
        // variantes están bloqueadas en el toggle → aquí solo entran simples.
        if (isset($this->item->warehouses)) {
            return max(0, (int) collect($this->item->warehouses)->sum('stock'));
        }
        // Fallback (relación no cargada / contexto de test sin BD).
        return max(0, (int) ($this->item->stock ?? 0));
    }

    /**
     * Precio de venta efectivo (lo que paga el cliente).
     */
    public function price(): float
    {
        $p = $this->variant
            ? ($this->variant->sale_unit_price ?: $this->item->sale_unit_price)
            : $this->item->sale_unit_price;
        return (float) $p;
    }

    /**
     * Resuelve precio regular + oferta para Saga.
     * En EBAEMY `compare_at_price` es el precio NORMAL tachado (mayor) y
     * `sale_unit_price` es el precio de venta. En Saga: Price=regular,
     * SalePrice=oferta con fechas. Solo hay oferta si compare_at_price > precio.
     *
     * @return array{price:float, sale_price:?float, sale_start:?string, sale_end:?string}
     */
    public function pricing(): array
    {
        $sale = $this->price();
        $compareAt = (float) ($this->item->compare_at_price ?? 0);

        if ($compareAt > $sale) {
            return [
                'price'      => round($compareAt, 2),
                'sale_price' => round($sale, 2),
                'sale_start' => $this->item->compare_at_from ? $this->item->compare_at_from->format('Y-m-d') : null,
                'sale_end'   => $this->item->compare_at_until ? $this->item->compare_at_until->format('Y-m-d') : null,
            ];
        }

        return ['price' => round($sale, 2), 'sale_price' => null, 'sale_start' => null, 'sale_end' => null];
    }

    /**
     * URLs públicas de las imágenes (principal primero), hasta 8. Salta el
     * placeholder. Saga exige URLs accesibles públicamente.
     */
    public function images(): array
    {
        $fqdn = $this->fqdn();
        if (!$fqdn) {
            return [];
        }

        $base = 'https://' . $fqdn . '/storage/uploads/items/';
        $placeholder = 'imagen-no-disponible.jpg';
        $urls = [];

        $main = $this->item->image;
        if ($main && $main !== $placeholder) {
            $urls[] = $base . $main;
        }

        foreach ($this->item->images as $img) {
            if ($img->image && $img->image !== $placeholder) {
                $urls[] = $base . $img->image;
            }
        }

        return array_values(array_unique($urls));
        // (el slice a 8 se aplica al construir el XML)
    }

    /**
     * Etiquetas de los atributos obligatorios de la categoría Saga que NO
     * cubrimos con los campos estándar.
     */
    public function missingMandatoryAttributes(): array
    {
        $sagaCat = $this->sagaCategoryId();
        if (!$sagaCat) {
            return [];
        }

        $cache = SagaCategoryAttribute::where('channel_id', $this->channel->id)
            ->where('saga_category_id', $sagaCat)
            ->first();
        if (!$cache) {
            return []; // sin caché aún → no bloqueamos por atributos
        }

        // Atributos que ya cubre el editor por producto (normalizados).
        $storedKeys = [];
        foreach (array_keys($this->storedAttributes()) as $k) {
            $storedKeys[] = $this->normalizeKey((string) $k);
        }

        $missing = [];
        foreach ($cache->mandatory() as $attr) {
            $key = $this->normalizeKey($attr['name'] ?? '');
            if ($key === ''
                || in_array($key, $this->providedAttrKeys, true)
                || in_array($key, $storedKeys, true)) {
                continue;
            }
            $missing[] = $attr['label'] ?? $attr['name'];
        }
        return $missing;
    }

    protected function normalizeKey(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($name));
    }

    protected function fqdn(): ?string
    {
        if ($this->fqdn !== null) {
            return $this->fqdn ?: null;
        }

        $env = app(Environment::class);
        $hostname = $env->hostname();
        if ($hostname && $hostname->fqdn) {
            return $this->fqdn = $hostname->fqdn;
        }

        $website = $env->tenant();
        if ($website) {
            $fqdn = Hostname::where('website_id', $website->id)->value('fqdn');
            return $this->fqdn = ($fqdn ?: '');
        }

        $this->fqdn = '';
        return null;
    }

    // ── Construcción del XML ────────────────────────────────────

    public function toArray(): array
    {
        $pricing = $this->pricing();
        return [
            'sku'                => $this->sku(),
            'name'               => $this->name(),
            'description'        => $this->description(),
            'short_description'  => $this->shortDescription(),
            'brand'              => $this->brand(),
            'primary_category'   => $this->sagaCategoryId(),
            'quantity'           => $this->quantity(),
            'pricing'            => $pricing,
            'images'             => $this->images(),
            'weight'             => (float) ($this->item->weight ?? 0),
            'length'             => (float) ($this->item->length ?? 0),
            'width'              => (float) ($this->item->width ?? 0),
            'height'             => (float) ($this->item->height ?? 0),
            'product_id'         => (string) ($this->item->barcode ?: $this->item->item_code_gs1 ?: $this->sku()),
        ];
    }

    /**
     * @param bool $includePrice  En ProductUpdate lo pasamos false: el seller
     *   maneja precio/ofertas EN Saga (el cron de precios está OFF a propósito),
     *   así que al re-publicar NO reenviamos price/sale_price y no los pisamos.
     *   En ProductCreate va true (Saga necesita el precio inicial).
     */
    public function toXml(bool $includePrice = true): string
    {
        $d = $this->toArray();
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $p = $d['pricing'];

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<Request><Product>';
        $xml .= '<PrimaryCategory>' . $e($d['primary_category']) . '</PrimaryCategory>';

        // Atributos a nivel producto
        $xml .= '<Attributes>';
        $xml .= '<name>' . $e($d['name']) . '</name>';
        $xml .= '<short_description>' . $e($d['short_description']) . '</short_description>';
        $xml .= '<description>' . $e($d['description']) . '</description>';
        $xml .= '<brand>' . $e($d['brand']) . '</brand>';
        // Atributos obligatorios de la categoría capturados por producto (Color,
        // Talla, Material…). La clave es el `name` del atributo de Saga; se omiten
        // los estándar para no duplicar name/brand/etc.
        foreach ($this->storedAttributes() as $attrName => $attrValue) {
            $tag = preg_replace('/[^A-Za-z0-9_]/', '', (string) $attrName);
            if ($tag === '' || in_array($this->normalizeKey($tag), $this->providedAttrKeys, true)) {
                continue;
            }
            if (is_array($attrValue)) {
                $attrValue = implode(',', $attrValue);
            }
            $xml .= '<' . $tag . '>' . $e($attrValue) . '</' . $tag . '>';
        }
        $xml .= '</Attributes>';

        // Skus: un <Sku> por VARIANTE (producto con variantes → Saga las agrupa
        // bajo este Product con selector color/talla) o un solo Sku (simple).
        $xml .= '<Skus>';
        $variants = $this->productVariantSkus();
        if (!empty($variants)) {
            foreach ($variants as $vs) {
                $xml .= $this->skuXml($vs['sku'], $vs['quantity'], $vs['pricing'], $vs['images'], $vs['variation'], $includePrice, $d);
            }
        } else {
            $xml .= $this->skuXml($d['sku'], (int) $d['quantity'], $p, $d['images'], [], $includePrice, $d);
        }
        $xml .= '</Skus>';
        $xml .= '</Product></Request>';

        return $xml;
    }

    /**
     * Construye UN <Sku> (producto simple o una variante). $d es el array de
     * producto (toArray) para los campos comunes (nombre, peso, dimensiones).
     * $variation = [NombreOpción => valor] → atributos de variación (Color/Talla).
     */
    private function skuXml(string $sku, int $qty, array $p, array $images, array $variation, bool $includePrice, array $d): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml  = '<Sku>';
        $xml .= '<SellerSku>' . $e($sku) . '</SellerSku>';
        $xml .= '<quantity>' . $qty . '</quantity>';
        if ($includePrice) {
            $xml .= '<price>' . number_format($p['price'], 2, '.', '') . '</price>';
            if (($p['sale_price'] ?? null) !== null) {
                $xml .= '<sale_price>' . number_format($p['sale_price'], 2, '.', '') . '</sale_price>';
                if (!empty($p['sale_start'])) $xml .= '<sale_start_date>' . $e($p['sale_start']) . '</sale_start_date>';
                if (!empty($p['sale_end']))   $xml .= '<sale_end_date>' . $e($p['sale_end']) . '</sale_end_date>';
            }
        }
        if (($d['product_id'] ?? '') !== '') {
            $xml .= '<package_content>' . $e($d['name']) . '</package_content>';
        }
        if (($d['weight'] ?? 0) > 0) $xml .= '<package_weight>' . number_format($d['weight'], 3, '.', '') . '</package_weight>';
        if (($d['length'] ?? 0) > 0) $xml .= '<package_length>' . number_format($d['length'], 2, '.', '') . '</package_length>';
        if (($d['width']  ?? 0) > 0) $xml .= '<package_width>'  . number_format($d['width'], 2, '.', '') . '</package_width>';
        if (($d['height'] ?? 0) > 0) $xml .= '<package_height>' . number_format($d['height'], 2, '.', '') . '</package_height>';

        // Atributos de variación (Color/Talla) a nivel SKU — distinguen cada
        // variante dentro del Product. El tag es el nombre de la opción.
        foreach ($variation as $name => $val) {
            $tag = preg_replace('/[^A-Za-z0-9_]/', '', (string) $name);
            if ($tag === '' || $val === null || $val === '') continue;
            $xml .= '<' . $tag . '>' . $e($val) . '</' . $tag . '>';
        }

        $imgs = array_slice($images, 0, 8);
        if (!empty($imgs)) {
            $xml .= '<Images>';
            foreach ($imgs as $url) {
                $xml .= '<Image>' . $e($url) . '</Image>';
            }
            $xml .= '</Images>';
        }

        $xml .= '</Sku>';
        return $xml;
    }

    /**
     * Datos de Sku por variante (productos con variantes). Vacío para simples.
     * Cada variante aporta su SellerSku, stock real (suma de almacenes), precio,
     * imagen y atributos de variación (Color/Talla) desde sus optionValues.
     */
    private function productVariantSkus(): array
    {
        if (empty($this->item->has_variants)) {
            return [];
        }
        $variants = $this->item->variants ?? null; // relación: solo activas
        if (!$variants || count($variants) === 0) {
            return [];
        }

        $base = $this->imageBase();
        $skuFallback = $this->item->internal_id ?: $this->item->item_code;
        $out = [];
        foreach ($variants as $v) {
            $vsku = (string) ($v->sku ?: ($skuFallback . '-V' . $v->id));
            // Stock real de la variante (suma item_variant_warehouse), igual
            // criterio que el padre (no item_variants.stock que puede estar stale).
            $qty = isset($v->warehouseStocks)
                ? (int) $v->warehouseStocks->sum('stock')
                : (int) ($v->stock ?? 0);
            $imgs = ($base && $v->image && $v->image !== 'imagen-no-disponible.jpg')
                ? [$base . $v->image]
                : $this->images();

            $out[] = [
                'sku'       => $vsku,
                'quantity'  => max(0, $qty),
                'pricing'   => $this->variantPricing($v),
                'images'    => $imgs,
                'variation' => $this->variantAttributes($v),
            ];
        }
        return $out;
    }

    private function variantPricing($v): array
    {
        $price = (float) ($v->sale_unit_price ?: $this->item->sale_unit_price);
        return ['price' => round($price, 2), 'sale_price' => null, 'sale_start' => null, 'sale_end' => null];
    }

    /** [Color => Rosado, Talla => M] desde las opciones de la variante. */
    private function variantAttributes($v): array
    {
        $attrs = [];
        try {
            foreach ($v->optionValues as $ov) {
                $name = $ov->option->name ?? null;
                if ($name) {
                    $attrs[$name] = $ov->value;
                }
            }
        } catch (\Throwable $e) {
            // optionValues no cargables (contexto de test) → sin variación.
        }
        return $attrs;
    }

    private function imageBase(): ?string
    {
        $fqdn = $this->fqdn();
        return $fqdn ? ('https://' . $fqdn . '/storage/uploads/items/') : null;
    }

    /**
     * XML mínimo para ACTIVAR/DESACTIVAR el listing en Saga sin tocar nada más
     * (reversible, no borra el producto). Se manda como ProductUpdate.
     * $status: 'active' | 'inactive'.
     */
    public function statusXml(string $status): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $status = $status === 'active' ? 'active' : 'inactive';

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<Request><Product><Skus><Sku>';
        $xml .= '<SellerSku>' . $e($this->sku()) . '</SellerSku>';
        $xml .= '<Status>' . $status . '</Status>';
        $xml .= '</Sku></Skus></Product></Request>';

        return $xml;
    }
}
