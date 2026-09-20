<?php

namespace Modules\Item\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
     
    public function authorize()
    {
        return true; 
    }
 
    public function messages()
    {
        return [
            'name.required'       => 'Escribe el nombre de la categoría.',
            'parent_id.different' => 'Una categoría no puede ser su propio grupo.',
        ];
    }

    public function rules()
    { 
        
        $id = $this->input('id');
        return [
             
            'name' => [
                'required',
            ],
            'parent_id' => [
                'nullable',
                'integer',
                // Que una categoría se ponga a sí misma de padre deja un nodo
                // apuntándose y el árbol deja de tener raíz.
                'different:id',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:999',
            ],
            'visible_ecommerce' => [
                'nullable',
                'boolean',
            ],
        ];

    }
}
