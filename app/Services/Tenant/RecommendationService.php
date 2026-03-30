<?php
namespace App\Services\Tenant;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Helper: siempre usar conexión tenant
// DB::connection('tenant')->table() usa 'system' por defecto, necesitamos 'tenant'

class RecommendationService
{
    /**
     * "Frequently bought together" - items that appear in the same orders
     */
    public function frequentlyBoughtTogether(int $itemId, int $limit = 4): array
    {
        return Cache::remember("reco_fbt_{$itemId}", 3600, function () use ($itemId, $limit) {
            // Find orders containing this item, then find other items in those orders
            $orderIds = DB::connection('tenant')->table('sale_note_items')
                ->where('item_id', $itemId)
                ->pluck('sale_note_id');

            if ($orderIds->isEmpty()) return [];

            return DB::connection('tenant')->table('sale_note_items as sni')
                ->join('items as i', 'i.id', '=', 'sni.item_id')
                ->whereIn('sni.sale_note_id', $orderIds)
                ->where('sni.item_id', '!=', $itemId)
                ->where('i.internal_id', '!=', null)
                ->select('i.id', 'i.description', 'i.sale_unit_price', 'i.image_small',
                    DB::raw('COUNT(*) as co_purchase_count'))
                ->groupBy('i.id', 'i.description', 'i.sale_unit_price', 'i.image_small')
                ->orderByDesc('co_purchase_count')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    /**
     * "Top selling" - best sellers in same category
     */
    public function topInCategory(int $itemId, int $limit = 4): array
    {
        return Cache::remember("reco_top_cat_{$itemId}", 3600, function () use ($itemId, $limit) {
            $categoryId = DB::connection('tenant')->table('items')->where('id', $itemId)->value('category_id');
            if (!$categoryId) return [];

            return DB::connection('tenant')->table('sale_note_items as sni')
                ->join('items as i', 'i.id', '=', 'sni.item_id')
                ->where('i.category_id', $categoryId)
                ->where('i.id', '!=', $itemId)
                ->where('i.internal_id', '!=', null)
                ->select('i.id', 'i.description', 'i.sale_unit_price', 'i.image_small',
                    DB::raw('SUM(sni.quantity) as total_sold'))
                ->groupBy('i.id', 'i.description', 'i.sale_unit_price', 'i.image_small')
                ->orderByDesc('total_sold')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    /**
     * "Recently viewed by others" - pseudo-recommendation
     */
    public function trending(int $limit = 8): array
    {
        return Cache::remember('reco_trending', 1800, function () use ($limit) {
            return DB::connection('tenant')->table('sale_note_items as sni')
                ->join('items as i', 'i.id', '=', 'sni.item_id')
                ->join('sale_notes as sn', 'sn.id', '=', 'sni.sale_note_id')
                ->where('sn.date_of_issue', '>=', now()->subDays(30)->format('Y-m-d'))
                ->where('sn.state_type_id', '01')
                ->where('i.internal_id', '!=', null)
                ->select('i.id', 'i.description', 'i.sale_unit_price', 'i.image_small',
                    DB::raw('SUM(sni.quantity) as total_sold'))
                ->groupBy('i.id', 'i.description', 'i.sale_unit_price', 'i.image_small')
                ->orderByDesc('total_sold')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }
}
