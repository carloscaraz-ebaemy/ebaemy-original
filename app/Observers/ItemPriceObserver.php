<?php

namespace App\Observers;

use App\Models\Tenant\Item;
use App\Models\Tenant\ItemPriceHistory;

class ItemPriceObserver
{
    public function updating(Item $item): void
    {
        if ($item->isDirty('sale_unit_price')) {
            $oldPrice = $item->getOriginal('sale_unit_price');
            $newPrice = $item->sale_unit_price;

            ItemPriceHistory::track(
                $item->id,
                (float) $oldPrice,
                (float) $newPrice,
                auth()->user()->email ?? 'system',
                'manual'
            );
        }
    }
}
