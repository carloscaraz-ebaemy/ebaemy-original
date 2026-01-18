<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Catalogs\AffectationIgvType;
use App\Models\Tenant\Item;

class ItemAffectationsIgvController extends Controller
{
    public function records()
    {
        $records = AffectationIgvType::all();

        return $records->transform(function($row) {
            return $row->getRowResource();
        });
    }

    public function changeActive($id, $active)
    {

        $validate = $this->validateAffectationItem($id);

        if ($validate) {
            return [
                'success' => false,
                'message' => 'No se puede desactivar esta afectación IGV porque está asociada a comprobantes/nota de venta.',
            ];
        }

        $record = AffectationIgvType::findOrFail($id);
        $record->active = $active;
        $record->save();


        return [
            'success' => true,
            'message' => 'Afectación IGV actualizada correctamente',
        ];
    }

    private function validateAffectationItem($id)
    {
       $validate = Item::where('sale_affectation_igv_type_id', $id)->exists(); 

       return $validate;

    }
}