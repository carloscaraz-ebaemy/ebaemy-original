<?php

namespace App\Providers;

use App\Models\Tenant\DocumentItem;
use App\Models\Tenant\Document;
use App\Models\Tenant\PurchaseItem;
use App\Models\Tenant\SaleNoteItem;
use App\Services\Tenant\SaleNoteStockService;
use Illuminate\Support\ServiceProvider;
use App\Traits\InventoryKardexTrait;

class InventoryKardexServiceProvider extends ServiceProvider
{

    use InventoryKardexTrait;
 
    
    public function register()
    {
        //
    }
 

    public function boot()
    {
        // $this->sale();     // Boleta/Factura — activar si se desea descuento por almacén en CPE
        // $this->purchase(); // Compras — activar si se desea ingreso por almacén en compras
        $this->sale_note();   // Notas de Venta — descuenta stock del almacén de la sucursal
    }

    private function sale()
    {

        DocumentItem::created(function ($document_item) {
            $document = Document::whereIn('document_type_id',['01','03'])->find($document_item->document_id);

            if($document){

                $inventory_kardex = $this->saveInventoryKardex($document, $document_item->item_id, $document->establishment_id, $document_item->quantity);
                
                if($document->state_type_id != 11){

                    $this->updateStock($document_item->item_id, $document->establishment_id, $inventory_kardex->quantity, true); 

                }
                
            }
        });
    }

    private function purchase()
    {
        PurchaseItem::created(function ($purchase_item) {

            $inventory_kardex = $this->saveInventoryKardex($purchase_item->purchase, $purchase_item->item_id, $purchase_item->purchase->establishment_id, $purchase_item->quantity);

            if($this->getItemWarehouse($purchase_item->item_id, $purchase_item->purchase->establishment_id)){
                $this->updateStock($purchase_item->item_id, $purchase_item->purchase->establishment_id, $inventory_kardex->quantity, false); 
            }else{
                $this->saveItemWarehouse($purchase_item->item_id, $purchase_item->purchase->establishment_id, $inventory_kardex->quantity);
            }
                
           
        });
    }

    private function sale_note()
    {
        // Toda la lógica de stock está en SaleNoteStockService.
        // Este ServiceProvider actúa solo como dispatcher de observers.
        $service = app(SaleNoteStockService::class);

        SaleNoteItem::created(fn($item) => $service->onItemCreated($item));
        SaleNoteItem::deleted(fn($item)  => $service->onItemDeleted($item));
    }
 
}
