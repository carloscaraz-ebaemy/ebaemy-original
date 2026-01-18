<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Person;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PersonCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function toArray($request)
    {
        return $this->collection->transform(function($row) {
            // Si $row es un recurso, obtenemos el modelo subyacente
            if ($row instanceof \Illuminate\Http\Resources\Json\JsonResource) {
                $row = $row->resource;
            }
        
            // Ahora $row es definitivamente un modelo Person
            return $row->getCollectionData();
        });
    }
}
