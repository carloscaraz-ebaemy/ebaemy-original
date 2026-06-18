<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Item;
use App\Models\Tenant\Company;
use App\Models\Tenant\ConfigurationEcommerce;
use Illuminate\Support\Str;

class ProductFeedController extends Controller
{
    private function getBaseData(): array
    {
        $domain  = request()->getScheme() . '://' . request()->getHost();
        $base    = $domain . '/ecommerce';
        $company = Company::first();
        $seo     = ConfigurationEcommerce::firstCached();

        return compact('domain', 'base', 'company', 'seo');
    }

    private function getProducts()
    {
        return Item::where('apply_store', 1)
            ->with(['category', 'warehouses', 'variants'])
            ->select(['id', 'slug', 'description', 'name', 'image', 'sale_unit_price',
                      'sale_unit_price_set', 'is_set', 'has_variants',
                      'currency_type_id', 'updated_at', 'stock', 'internal_id'])
            ->get();
    }

    /**
     * Stock efectivo: en productos con variantes el stock vive en las variantes,
     * no en item_warehouse del padre.
     */
    private function resolveStock($product): float
    {
        if ($product->has_variants && $product->variants->isNotEmpty()) {
            return (float) $product->variants->sum('stock');
        }

        $stock = 0;
        foreach ($product->warehouses as $wh) {
            $stock += $wh->stock;
        }
        return (float) $stock;
    }

    /**
     * Precio efectivo: en productos con variantes el padre suele tener 0/null,
     * Meta rechaza precio 0 → usamos el precio mínimo de variante con precio válido.
     */
    private function resolvePrice($product): float
    {
        if ($product->has_variants && $product->variants->isNotEmpty()) {
            $prices = $product->variants
                ->pluck('sale_unit_price')
                ->filter(fn ($p) => (float) $p > 0);

            if ($prices->isNotEmpty()) {
                return (float) $prices->min();
            }
        }

        if ($product->is_set && $product->sale_unit_price_set) {
            return (float) $product->sale_unit_price_set;
        }

        return (float) $product->sale_unit_price;
    }

    /**
     * Imagen efectiva: si el padre no tiene foto (caso típico en productos con
     * variantes) tomamos la primera imagen válida de las variantes.
     */
    private function resolveImageUrl($product): string
    {
        $valid = fn ($img) => $img && $img !== 'imagen-no-disponible.jpg';

        if ($valid($product->image)) {
            return asset('storage/uploads/items/' . $product->image);
        }

        if ($product->has_variants && $product->variants->isNotEmpty()) {
            $variantImg = $product->variants
                ->pluck('image')
                ->first(fn ($img) => $valid($img));

            if ($variantImg) {
                return asset('storage/uploads/items/' . $variantImg);
            }
        }

        return asset('logo/imagen-no-disponible.jpg');
    }

    /**
     * Google Merchant Center XML Feed
     * GET /ecommerce/feed/google
     */
    public function googleMerchant()
    {
        ['domain' => $domain, 'base' => $base, 'company' => $company] = $this->getBaseData();
        $products = $this->getProducts();

        $storeName = $company->trade_name ?? $company->name ?? 'Tienda Online';
        $currency  = 'PEN';

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
        $xml .= '<channel>' . "\n";
        $xml .= '  <title>' . htmlspecialchars($storeName) . '</title>' . "\n";
        $xml .= '  <link>' . htmlspecialchars($base) . '</link>' . "\n";
        $xml .= '  <description>Catálogo de productos de ' . htmlspecialchars($storeName) . '</description>' . "\n";

        foreach ($products as $product) {
            $slug        = $product->slug ?: $product->id;
            $productUrl  = $base . '/item/' . $slug;
            $imageUrl    = $this->resolveImageUrl($product);

            $stock        = $this->resolveStock($product);
            $availability = $stock > 0 ? 'in stock' : 'out of stock';
            $price        = number_format($this->resolvePrice($product), 2, '.', '');
            $categoryName = $product->category ? $product->category->name : 'General';
            $description  = $product->name ?: $product->description;

            $xml .= '  <item>' . "\n";
            $xml .= '    <g:id>'              . htmlspecialchars($product->id) . '</g:id>' . "\n";
            $xml .= '    <g:title>'           . htmlspecialchars($product->description) . '</g:title>' . "\n";
            $xml .= '    <g:description>'     . htmlspecialchars(Str::limit($description, 500)) . '</g:description>' . "\n";
            $xml .= '    <g:link>'            . htmlspecialchars($productUrl) . '</g:link>' . "\n";
            $xml .= '    <g:image_link>'      . htmlspecialchars($imageUrl) . '</g:image_link>' . "\n";
            $xml .= '    <g:availability>'    . $availability . '</g:availability>' . "\n";
            $xml .= '    <g:price>'           . $price . ' ' . $currency . '</g:price>' . "\n";
            $xml .= '    <g:google_product_category>' . htmlspecialchars($categoryName) . '</g:google_product_category>' . "\n";
            $xml .= '    <g:brand>'           . htmlspecialchars($storeName) . '</g:brand>' . "\n";
            $xml .= '    <g:condition>new</g:condition>' . "\n";
            $xml .= '    <g:identifier_exists>no</g:identifier_exists>' . "\n";
            $xml .= '  </item>' . "\n";
        }

        $xml .= '</channel>' . "\n";
        $xml .= '</rss>';

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Facebook / Instagram Catalog JSON Feed
     * GET /ecommerce/feed/facebook
     */
    public function facebookCatalog()
    {
        ['base' => $base, 'company' => $company] = $this->getBaseData();
        $products  = $this->getProducts();
        $storeName = $company->trade_name ?? $company->name ?? 'Tienda Online';
        $currency  = 'PEN';

        $headers = [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ];

        $callback = function () use ($products, $base, $storeName, $currency) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'id', 'title', 'description', 'availability', 'condition',
                'price', 'link', 'image_link', 'brand',
            ]);

            foreach ($products as $product) {
                $slug       = $product->slug ?: $product->id;
                $productUrl = $base . '/item/' . $slug;
                $imageUrl   = $this->resolveImageUrl($product);

                $stock        = $this->resolveStock($product);
                $availability = $stock > 0 ? 'in stock' : 'out of stock';
                $price        = number_format($this->resolvePrice($product), 2, '.', '') . ' ' . $currency;
                $description  = $product->name ?: $product->description;

                fputcsv($out, [
                    (string)$product->id,
                    Str::limit($product->description, 65),
                    Str::limit($description, 500),
                    $availability,
                    'new',
                    $price,
                    $productUrl,
                    $imageUrl,
                    $storeName,
                ]);
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * CSV Feed (TikTok Shop / generic)
     * GET /ecommerce/feed/csv
     */
    public function csvFeed()
    {
        ['base' => $base, 'company' => $company] = $this->getBaseData();
        $products  = $this->getProducts();
        $storeName = $company->trade_name ?? $company->name ?? 'Tienda Online';
        $currency  = 'PEN';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="products.csv"',
            'Cache-Control'       => 'public, max-age=3600',
        ];

        $callback = function () use ($products, $base, $storeName, $currency) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fputs($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'id', 'title', 'description', 'availability', 'condition',
                'price', 'link', 'image_link', 'brand', 'category',
            ]);

            foreach ($products as $product) {
                $slug       = $product->slug ?: $product->id;
                $productUrl = $base . '/item/' . $slug;
                $imageUrl   = $this->resolveImageUrl($product);

                $stock        = $this->resolveStock($product);
                $availability = $stock > 0 ? 'in stock' : 'out of stock';
                $price        = number_format($this->resolvePrice($product), 2, '.', '') . ' ' . $currency;
                $description  = $product->name ?: $product->description;
                $categoryName = $product->category ? $product->category->name : '';

                fputcsv($out, [
                    (string)$product->id,
                    $product->description,
                    \Illuminate\Support\Str::limit($description, 500),
                    $availability,
                    'new',
                    $price,
                    $productUrl,
                    $imageUrl,
                    $storeName,
                    $categoryName,
                ]);
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * TikTok Catalog Feed (CSV)
     * Formato oficial: https://ads.tiktok.com/help/article/product-catalog-specs
     * GET /ecommerce/feed/tiktok
     */
    public function tiktokCatalog()
    {
        ['base' => $base, 'company' => $company] = $this->getBaseData();
        $products  = $this->getProducts();
        $storeName = $company->trade_name ?? $company->name ?? 'Tienda Online';
        $currency  = 'PEN';

        $headers = [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ];

        $callback = function () use ($products, $base, $storeName, $currency) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM
            fputs($out, "\xEF\xBB\xBF");

            // Columnas requeridas y recomendadas por TikTok Catalog
            fputcsv($out, [
                'sku_id',
                'title',
                'description',
                'availability',
                'condition',
                'price',
                'link',
                'image_link',
                'brand',
                'google_product_category',
                'item_group_id',
            ]);

            foreach ($products as $product) {
                $slug       = $product->slug ?: $product->id;
                $productUrl = $base . '/item/' . $slug;
                $imageUrl   = $this->resolveImageUrl($product);

                $stock        = $this->resolveStock($product);
                $availability = $stock > 0 ? 'in stock' : 'out of stock';
                $price        = number_format($this->resolvePrice($product), 2, '.', '') . ' ' . $currency;
                $description  = $product->name ?: $product->description;
                $categoryName = $product->category ? $product->category->name : 'General';

                fputcsv($out, [
                    (string) $product->id,                          // sku_id
                    Str::limit($product->description, 150),         // title (máx 150)
                    Str::limit(strip_tags($description), 5000),     // description
                    $availability,                                  // availability
                    'new',                                          // condition
                    $price,                                         // price
                    $productUrl,                                    // link
                    $imageUrl,                                      // image_link
                    $storeName,                                     // brand
                    $categoryName,                                  // google_product_category
                    $product->is_set ? 'pack-' . $product->id : '', // item_group_id
                ]);
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}
