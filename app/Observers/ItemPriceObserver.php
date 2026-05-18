<?php

namespace App\Observers;

use App\Models\Tenant\Item;
use App\Models\Tenant\ItemPriceHistory;
use App\Models\Tenant\PricingSettings;
use App\Services\Tenant\Pricing\PriceCalculator;

/**
 * Audita cambios de precio Y costo en items.
 *
 * Fase 1 rediseño precios (2026-05-18): además de sale_unit_price ahora
 * trackea purchase_unit_price y guarda el margen efectivo al momento del
 * cambio. Permite analizar erosión de margen y origen del cambio.
 *
 * También recalcula floor_price si cambió costo, landed_cost_extra_pct o
 * min_margin_pct (mantener el guardrail siempre consistente).
 */
class ItemPriceObserver
{
    public function saving(Item $item): void
    {
        // Recalcular floor_price si cambia cost, landed_cost_extra_pct o min_margin_pct
        $costFields = ['purchase_unit_price', 'landed_cost_extra_pct', 'min_margin_pct'];
        $anyDirty = false;
        foreach ($costFields as $f) {
            if ($item->isDirty($f)) {
                $anyDirty = true;
                break;
            }
        }

        if ($anyDirty || !$item->exists) {
            $cost     = (float) $item->purchase_unit_price;
            $extraPct = (float) ($item->landed_cost_extra_pct ?? 0);
            $minPct   = $item->min_margin_pct !== null ? (float) $item->min_margin_pct : 0.0;

            if ($cost > 0) {
                $effective = PriceCalculator::effectiveCost($cost, $extraPct);
                $item->floor_price          = PriceCalculator::floorPrice($effective, $minPct);
                $item->floor_price_recalc_at = now();
            }
        }
    }

    public function updating(Item $item): void
    {
        $priceChanged = $item->isDirty('sale_unit_price');
        $costChanged  = $item->isDirty('purchase_unit_price');

        if (!$priceChanged && !$costChanged) {
            return;
        }

        $settings = PricingSettings::find(1);
        if ($settings && !$settings->audit_cost_changes && !$priceChanged) {
            // Tenant deshabilitó auditoría de costo y solo cambió costo
            return;
        }

        $oldPrice = $priceChanged ? (float) $item->getOriginal('sale_unit_price') : null;
        $newPrice = $priceChanged ? (float) $item->sale_unit_price : null;
        $oldCost  = $costChanged  ? (float) $item->getOriginal('purchase_unit_price') : null;
        $newCost  = $costChanged  ? (float) $item->purchase_unit_price : null;

        // Margen efectivo al momento del cambio (con valores nuevos)
        $currentPrice = $newPrice ?? (float) $item->sale_unit_price;
        $currentCost  = $newCost  ?? (float) $item->purchase_unit_price;
        $extraPct     = (float) ($item->landed_cost_extra_pct ?? 0);
        $marginAtChange = null;
        if ($currentPrice > 0 && $currentCost > 0) {
            $effective = PriceCalculator::effectiveCost($currentCost, $extraPct);
            $marginAtChange = PriceCalculator::marginPct($currentPrice, $effective);
        }

        ItemPriceHistory::trackChange(
            $item->id,
            $oldPrice,
            $newPrice,
            $oldCost,
            $newCost,
            $marginAtChange,
            auth()->user()->email ?? 'system',
            'manual'
        );
    }
}
