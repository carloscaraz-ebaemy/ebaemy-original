<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->input('id');
     
        return [
            'name' => [
                'required'
            ],
            'description' => [
                'nullable'
            ],
            'item_id' => [
                'nullable',
                'integer',
                'exists:tenant.items,id'
            ],
            'image' => ['required']
        ];
    }

    public function messages()
    {
        return [
            'item_id.integer' => 'El campo Producto debe ser un número.',
            'item_id.exists' => 'El producto seleccionado no existe.',
        ];
    }
}