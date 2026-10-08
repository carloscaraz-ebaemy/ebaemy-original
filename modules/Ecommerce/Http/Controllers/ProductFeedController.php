<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Company;
use App\Models\Tenant\ConfigurationEcommerce;
use App\Services\Tenant\Feed\ProductFeedRowBuilder;

/**
 * Feeds de catálogo de la tienda del tenant.
 *
 * Cada fila es un item vendible: en los productos con variantes se emite UNA
 * por variante, agrupadas con `item_group_id`. Quien decide eso es
 * ProductFeedRowBuilder; aquí sólo se le da formato a cada plataforma.
 */
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

    private function builder(): ProductFeedRowBuilder
    {
        ['base' => $base, 'company' => $company] = $this->getBaseData();

        $storeName = $company->trade_name ?: ($company->name ?: 'Tienda Online');

        return new ProductFeedRowBuilder($base, $storeName, 'PEN');
    }

    /**
     * Google Merchant Center XML Feed
     * GET /ecommerce/feed/google
     */
    public function googleMerchant()
    {
        ['base' => $base, 'company' => $company] = $this->getBaseData();
        $storeName = $company->trade_name ?: ($company->name ?: 'Tienda Online');

        $feed = $this->builder();
        $rows = $feed->rows();

        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_XML1);

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
        $xml .= '<channel>' . "\n";
        $xml .= '  <title>' . $esc($storeName) . '</title>' . "\n";
        $xml .= '  <link>' . $esc($base) . '</link>' . "\n";
        $xml .= '  <description>Catálogo de productos de ' . $esc($storeName) . '</description>' . "\n";

        foreach ($rows as $row) {
            $xml .= '  <item>' . "\n";
            $xml .= '    <g:id>'          . $esc($row['id']) . '</g:id>' . "\n";
            $xml .= '    <g:item_group_id>' . $esc($row['group_id']) . '</g:item_group_id>' . "\n";
            $xml .= '    <g:title>'       . $esc($feed->title($row)) . '</g:title>' . "\n";
            $xml .= '    <g:description>' . $esc($feed->description($row, 500)) . '</g:description>' . "\n";
            $xml .= '    <g:link>'        . $esc($row['link']) . '</g:link>' . "\n";
            $xml .= '    <g:image_link>'  . $esc($row['image_link']) . '</g:image_link>' . "\n";

            foreach ($row['extra_images'] as $extra) {
                $xml .= '    <g:additional_image_link>' . $esc($extra) . '</g:additional_image_link>' . "\n";
            }

            $xml .= '    <g:availability>' . $feed->availability($row) . '</g:availability>' . "\n";
            $xml .= '    <g:price>'        . $feed->formatPrice($row) . '</g:price>' . "\n";
            $xml .= '    <g:google_product_category>' . $esc($row['category']) . '</g:google_product_category>' . "\n";
            $xml .= '    <g:brand>'        . $esc($row['brand']) . '</g:brand>' . "\n";
            $xml .= '    <g:condition>new</g:condition>' . "\n";
            $xml .= '    <g:identifier_exists>no</g:identifier_exists>' . "\n";

            if ($row['sku']) {
                $xml .= '    <g:mpn>' . $esc(mb_substr($row['sku'], 0, 70)) . '</g:mpn>' . "\n";
            }
            if ($row['color']) {
                $xml .= '    <g:color>' . $esc(mb_substr($row['color'], 0, 100)) . '</g:color>' . "\n";
            }
            if ($row['size']) {
                $xml .= '    <g:size>' . $esc(mb_substr($row['size'], 0, 100)) . '</g:size>' . "\n";
            }

            $xml .= '  </item>' . "\n";
        }

        $xml .= '</channel>' . "\n";
        $xml .= '</rss>';

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Facebook / Instagram / WhatsApp Catalog Feed (CSV)
     * GET /ecommerce/feed/facebook
     *
     * `item_group_id` es lo que hace que Meta pinte las variantes como un solo
     * producto con selector de color y talla en vez de productos sueltos; sin
     * él el catálogo de WhatsApp sólo mostraba el item principal.
     */
    public function facebookCatalog()
    {
        $feed = $this->builder();
        $rows = $feed->rows();

        $headers = [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ];

        $callback = function () use ($feed, $rows) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'id', 'item_group_id', 'title', 'description', 'availability', 'condition',
                'price', 'link', 'image_link', 'additional_image_link', 'brand',
                'color', 'size', 'quantity_to_sell_on_facebook',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['id'],
                    $row['group_id'],
                    $feed->title($row, 65),
                    $feed->description($row, 500),
                    $feed->availability($row),
                    'new',
                    $feed->formatPrice($row),
                    $row['link'],
                    $row['image_link'],
                    implode(',', $row['extra_images']),
                    $row['brand'],
                    $row['color'] ?: '',
                    $row['size'] ?: '',
                    (int) $row['inventory'],
                ]);
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * CSV Feed (genérico)
     * GET /ecommerce/feed/csv
     */
    public function csvFeed()
    {
        $feed = $this->builder();
        $rows = $feed->rows();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="products.csv"',
            'Cache-Control'       => 'public, max-age=3600',
        ];

        $callback = function () use ($feed, $rows) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fputs($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'id', 'item_group_id', 'title', 'description', 'availability', 'condition',
                'price', 'link', 'image_link', 'brand', 'category', 'color', 'size', 'quantity',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['id'],
                    $row['group_id'],
                    $row['title'],
                    $feed->description($row, 500),
                    $feed->availability($row),
                    'new',
                    $feed->formatPrice($row),
                    $row['link'],
                    $row['image_link'],
                    $row['brand'],
                    $row['category'],
                    $row['color'] ?: '',
                    $row['size'] ?: '',
                    (int) $row['inventory'],
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
        $feed = $this->builder();
        $rows = $feed->rows();

        $headers = [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ];

        $callback = function () use ($feed, $rows) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM
            fputs($out, "\xEF\xBB\xBF");

            // Columnas requeridas y recomendadas por TikTok Catalog
            fputcsv($out, [
                'sku_id',
                'item_group_id',
                'title',
                'description',
                'availability',
                'condition',
                'price',
                'link',
                'image_link',
                'brand',
                'google_product_category',
                'color',
                'size',
                'quantity',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['id'],                        // sku_id
                    $row['group_id'],                  // item_group_id
                    $feed->title($row, 150),           // title (máx 150)
                    $feed->description($row, 5000),    // description
                    $feed->availability($row),
                    'new',
                    $feed->formatPrice($row),
                    $row['link'],
                    $row['image_link'],
                    $row['brand'],
                    $row['category'],
                    $row['color'] ?: '',
                    $row['size'] ?: '',
                    (int) $row['inventory'],
                ]);
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}
