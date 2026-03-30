<?php

namespace App\Services\Marketplace;

use App\Models\Tenant\MarketplaceChannel;
use App\Models\Tenant\MarketplaceProduct;
use App\Models\Tenant\MarketplaceSyncLog;
use App\Models\Tenant\Item;
use Illuminate\Support\Facades\Storage;

/**
 * Meta Commerce — Facebook/Instagram Shopping
 *
 * Genera feeds XML/CSV para catálogos de productos
 * Compatible con: Facebook Catalog, Instagram Shopping, WhatsApp Commerce
 *
 * Campos obligatorios Meta:
 * id, title, description, availability, condition, price, link, image_link, brand
 */
class MetaFeedService
{
    protected MarketplaceChannel $channel;
    protected string $baseUrl;

    public function __construct(MarketplaceChannel $channel)
    {
        $this->channel = $channel;
        $this->baseUrl = $channel->getCredential('store_url', url('/ecommerce'));
    }

    // ══════════════════════════════════════════════════════════════
    // XML FEED — Compatible con Facebook/Instagram Catalog
    // ══════════════════════════════════════════════════════════════

    /**
     * Generar feed XML completo para Meta Commerce
     */
    public function generateXmlFeed(): string
    {
        $items = Item::where('apply_store', 1)
            ->whereNotNull('internal_id')
            ->with(['currency_type', 'category', 'brand', 'variants', 'images', 'warehouses'])
            ->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<feed xmlns="http://www.w3.org/2005/Atom" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
        $xml .= "  <title>Catálogo de productos</title>\n";
        $xml .= "  <link href=\"{$this->baseUrl}\" rel=\"alternate\" type=\"text/html\"/>\n";
        $xml .= "  <updated>" . now()->toIso8601String() . "</updated>\n";

        foreach ($items as $item) {
            if ($item->has_variants && $item->variants->where('is_active', true)->count() > 0) {
                // Generar una entrada por variante
                foreach ($item->variants->where('is_active', true) as $variant) {
                    $xml .= $this->buildEntry($item, $variant);
                }
            } else {
                $xml .= $this->buildEntry($item);
            }
        }

        $xml .= "</feed>\n";

        // Guardar en storage público
        $path = 'feeds/meta-catalog.xml';
        Storage::disk('public')->put($path, $xml);

        // Log
        MarketplaceSyncLog::log($this->channel->id, 'generate_feed', 'push', function ($log) use ($items) {
            return ['processed' => $items->count(), 'success' => $items->count(), 'failed' => 0];
        });

        return $xml;
    }

    protected function buildEntry(Item $item, $variant = null): string
    {
        $id = $variant ? "{$item->internal_id}-{$variant->sku}" : $item->internal_id;
        $title = $variant ? "{$item->description} - {$variant->display_name}" : $item->description;
        $price = $variant ? ($variant->sale_unit_price ?: $item->sale_unit_price) : $item->sale_unit_price;
        $stock = $variant ? (int) $variant->stock : (int) $item->stock;
        $availability = $stock > 0 ? 'in stock' : 'out of stock';
        $currency = $item->currency_type->id ?? 'PEN';
        $link = "{$this->baseUrl}/item/" . ($item->slug ?: $item->id);
        $brand = $item->brand->name ?? 'Sin marca';
        $category = $item->category->name ?? 'General';

        // Imagen
        $image = $item->image && $item->image !== 'imagen-no-disponible.jpg'
            ? url("storage/uploads/items/{$item->image}")
            : url('logo/imagen-no-disponible.jpg');

        if ($variant && $variant->image) {
            $image = url("storage/uploads/items/{$variant->image}");
        }

        // Imágenes adicionales
        $additionalImages = '';
        if ($item->images && $item->images->count() > 0) {
            foreach ($item->images->take(5) as $img) {
                if ($img->image && $img->image !== 'imagen-no-disponible.jpg') {
                    $imgUrl = url("storage/uploads/items/{$img->image}");
                    $additionalImages .= "    <g:additional_image_link>" . e($imgUrl) . "</g:additional_image_link>\n";
                }
            }
        }

        $description = strip_tags($item->name ?: $item->description);
        $description = substr($description, 0, 5000);

        return "  <entry>\n"
             . "    <g:id>" . e($id) . "</g:id>\n"
             . "    <g:title>" . e(substr($title, 0, 150)) . "</g:title>\n"
             . "    <g:description>" . e($description) . "</g:description>\n"
             . "    <g:link>" . e($link) . "</g:link>\n"
             . "    <g:image_link>" . e($image) . "</g:image_link>\n"
             . $additionalImages
             . "    <g:availability>{$availability}</g:availability>\n"
             . "    <g:price>" . number_format($price, 2, '.', '') . " {$currency}</g:price>\n"
             . "    <g:brand>" . e($brand) . "</g:brand>\n"
             . "    <g:condition>new</g:condition>\n"
             . "    <g:product_type>" . e($category) . "</g:product_type>\n"
             . "    <g:item_group_id>" . e($item->internal_id) . "</g:item_group_id>\n"
             . ($variant ? "    <g:size>" . e($variant->display_name) . "</g:size>\n" : '')
             . "    <g:inventory>" . $stock . "</g:inventory>\n"
             . "  </entry>\n";
    }

    // ══════════════════════════════════════════════════════════════
    // CSV FEED — Alternativa para importación directa
    // ══════════════════════════════════════════════════════════════

    public function generateCsvFeed(): string
    {
        $items = Item::where('apply_store', 1)
            ->whereNotNull('internal_id')
            ->with(['currency_type', 'category', 'brand', 'variants', 'warehouses'])
            ->get();

        $header = "id,title,description,availability,condition,price,link,image_link,brand,product_type,inventory\n";
        $rows = '';

        foreach ($items as $item) {
            if ($item->has_variants && $item->variants->where('is_active', true)->count() > 0) {
                foreach ($item->variants->where('is_active', true) as $variant) {
                    $rows .= $this->buildCsvRow($item, $variant);
                }
            } else {
                $rows .= $this->buildCsvRow($item);
            }
        }

        $csv = $header . $rows;
        Storage::disk('public')->put('feeds/meta-catalog.csv', $csv);
        return $csv;
    }

    protected function buildCsvRow(Item $item, $variant = null): string
    {
        $id = $variant ? "{$item->internal_id}-{$variant->sku}" : $item->internal_id;
        $title = $variant ? "{$item->description} - {$variant->display_name}" : $item->description;
        $price = $variant ? ($variant->sale_unit_price ?: $item->sale_unit_price) : $item->sale_unit_price;
        $stock = $variant ? (int) $variant->stock : (int) $item->stock;
        $availability = $stock > 0 ? 'in stock' : 'out of stock';
        $link = "{$this->baseUrl}/item/" . ($item->slug ?: $item->id);
        $image = $item->image ? url("storage/uploads/items/{$item->image}") : '';
        $brand = $item->brand->name ?? '';
        $category = $item->category->name ?? '';

        return '"' . str_replace('"', '""', $id) . '",'
             . '"' . str_replace('"', '""', substr($title, 0, 150)) . '",'
             . '"' . str_replace('"', '""', substr($item->description, 0, 500)) . '",'
             . '"' . $availability . '",'
             . '"new",'
             . '"' . number_format($price, 2, '.', '') . ' PEN",'
             . '"' . $link . '",'
             . '"' . $image . '",'
             . '"' . str_replace('"', '""', $brand) . '",'
             . '"' . str_replace('"', '""', $category) . '",'
             . $stock . "\n";
    }
}
