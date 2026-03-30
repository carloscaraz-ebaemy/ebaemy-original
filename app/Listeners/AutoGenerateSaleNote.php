<?php

namespace App\Listeners;

use App\Services\Tenant\OrderToSaleNoteService;
use Illuminate\Support\Facades\Log;

class AutoGenerateSaleNote
{
    public function handle($event)
    {
        $order = $event->order ?? null;
        if (!$order) return;

        // Only auto-generate for cash payments (already confirmed at creation)
        $ref = strtolower($order->reference_payment ?? '');
        if (str_contains($ref, 'cash') || str_contains($ref, 'efectivo')) {
            try {
                $service = app(OrderToSaleNoteService::class);
                $service->generate($order);
            } catch (\Throwable $e) {
                Log::warning('Auto SaleNote generation failed for cash order', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
