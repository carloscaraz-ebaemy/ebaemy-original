<?php

namespace Modules\Report\Http\Resources;

use App\Models\Tenant\PurchaseItem;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ItemCollection extends ResourceCollection
{


    public function toArray($request) {


        return $this->collection->transform(function ($row, $key) {


            $observation = null;
            $class = get_class($row);
            if ($class == PurchaseItem::class) {
                /** @var \App\Models\Tenant\PurchaseItem $row */
                $document = $row->purchase;
                $customer_name = $document->supplier->name;
                $customer_number = $document->supplier->number;
                /** @var \App\Models\Tenant\PurchaseItem $row */
                $purchase = $row->purchase;
                $observation=$purchase->observation;
            } else {
                /** @var \App\Models\Tenant\DocumentItem $row */
                $document = $row->document;
                $customer_name = $document->customer->name;
                $customer_number = $document->customer->number;
            }
            // Qué variante se vendió. El dato ya estaba: el snapshot JSON de la
            // línea guarda variant_id y variant_display_name desde que el POS
            // aprendió a elegir variante. Nadie lo leía, así que el reporte
            // mostraba doce líneas idénticas de "Zapatilla" sin decir la talla.
            $variantName = null;
            try {
                $snapshot = $row->item;
                $variantName = $snapshot->variant_display_name
                    ?? ($snapshot->variant_id ? ('variante #' . $snapshot->variant_id) : null);
            } catch (\Throwable $e) {
                // Snapshot antiguo o malformado: la línea se muestra sin variante.
            }

            return [
                'id'                        => $row->id,
                'variant_name'              => $variantName,
                'date_of_issue'             => $document->date_of_issue->format('Y-m-d'),
                'customer_name'             => $customer_name,
                'customer_number'           => $customer_number,
                'series'                    => $document->series,
                'alone_number'              => $document->number,
                'quantity'                  => number_format($row->quantity, 2),
                'total'                     => (in_array($document->document_type_id,
                                                         ['01', '03']) && in_array($document->state_type_id,
                                                                                   ['09', '11'])) ? number_format(0, 2)
                    : number_format($row->total, 2),
                'document_type_description' => $document->document_type->description,
                'document_type_id'          => $document->document_type->id,
                'web_platform_name'         => optional($row->relation_item->web_platform)->name,
                'observation'=>$observation,

            ];
        });
    }
}
