<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Order;
use App\Models\Tenant\Item;
use Illuminate\Support\Facades\Cache;

class SocialProofService
{
    public function getRecentPurchases(int $limit = 5): array
    {
        return Cache::remember('social_proof_recent', 300, function () use ($limit) {
            return Order::where('status_order_id', '!=', 5)
                ->where('created_at', '>=', now()->subHours(48))
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(function ($order) {
                    $customer = is_array($order->customer) ? $order->customer : json_decode($order->customer, true);
                    $name = $customer['apellidos_y_nombres_o_razon_social'] ?? 'Alguien';
                    $firstName = explode(' ', trim($name))[0];
                    $items = is_array($order->items) ? $order->items : json_decode($order->items, true);
                    $firstItem = $items[0] ?? null;
                    return [
                        'name' => $firstName,
                        'city' => $customer['distrito'] ?? $customer['ciudad'] ?? null,
                        'product' => $firstItem['item']['description'] ?? $firstItem['description'] ?? 'un producto',
                        'time_ago' => $order->created_at->diffForHumans(),
                    ];
                })->toArray();
        });
    }

    public function getProductStats(int $itemId): array
    {
        return Cache::remember("social_proof_item_{$itemId}", 600, function () use ($itemId) {
            $totalSold = \App\Models\Tenant\SaleNoteItem::where('item_id', $itemId)->sum('quantity');
            $viewersNow = rand(2, 15); // Simulated - replace with real analytics
            return [
                'total_sold' => (int) $totalSold,
                'viewers_now' => $viewersNow,
                'last_purchased' => Order::where('status_order_id', '!=', 5)
                    ->whereJsonContains('items', [['item_id' => $itemId]])
                    ->max('created_at'),
            ];
        });
    }
}
