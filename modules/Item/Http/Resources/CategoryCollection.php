<?php

namespace Modules\Item\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Modules\Item\Models\Category;

class CategoryCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param Request $request
     *
     * @return array
     */
    public function toArray($request)
    {
        // Nombre del padre y conteo de productos en dos consultas, no una por
        // fila: el listado pagina de 20 en 20 pero se abre muchas veces al dia.
        $parents = Category::whereNull('parent_id')->pluck('name', 'id');

        $counts = \Illuminate\Support\Facades\DB::connection('tenant')
            ->table('items')
            ->whereIn('category_id', $this->collection->pluck('id'))
            ->selectRaw('category_id, COUNT(*) AS total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        return $this->collection->transform(function ($row, $key) use ($parents, $counts) {

            return [
                'id' => $row->id,
                'name' => $row->name,
                'parent_id' => $row->parent_id,
                'parent_name' => $row->parent_id ? ($parents[$row->parent_id] ?? null) : null,
                'sort_order' => (int) $row->sort_order,
                'visible_ecommerce' => (bool) $row->visible_ecommerce,
                'items_count' => (int) ($counts[$row->id] ?? 0),
                'image' => $row->image,
                'image_url' => ($row->image !== 'imagen-no-disponible.jpg') ? asset('storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'categories' . DIRECTORY_SEPARATOR . $row->image) : asset("/logo/{$row->image}"),
                'created_at' => ($row->created_at) ? $row->created_at->format('d-m-Y h:iA') : null,
                'updated_at' => ($row->updated_at) ? $row->updated_at->format('Y-m-d H:i:s') : null,
            ];
        });

    }

}
