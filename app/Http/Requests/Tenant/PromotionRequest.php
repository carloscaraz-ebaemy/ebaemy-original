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
            'image' => ['required'],

            // Slider administrable (fase 3). Todo opcional: un banner que es
            // pura imagen sigue siendo válido.
            'title'            => ['nullable', 'string', 'max:255'],
            'subtitle'         => ['nullable', 'string', 'max:500'],
            'button_text'      => ['nullable', 'string', 'max:60'],
            'link_type'        => ['nullable', Rule::in(['product', 'url', 'category', 'none'])],
            'link_category_id' => ['nullable', 'integer', 'exists:tenant.categories,id'],
            'banner_url'       => ['nullable', 'url', 'max:500'],
            'sort_order'       => ['nullable', 'integer', 'min:0', 'max:9999'],
            'starts_at'        => ['nullable', 'date'],
            'ends_at'          => ['nullable', 'date'],
        ];
    }

    public function messages()
    {
        return [
            'item_id.integer' => 'El campo Producto debe ser un número.',
            'item_id.exists' => 'El producto seleccionado no existe.',
            'link_category_id.exists' => 'La categoría seleccionada no existe.',
            'banner_url.url' => 'El enlace debe ser una URL válida (incluye https://).',
            'title.max' => 'El título no puede pasar de 255 caracteres.',
            'subtitle.max' => 'El subtítulo no puede pasar de 500 caracteres.',
            'button_text.max' => 'El texto del botón no puede pasar de 60 caracteres.',
        ];
    }
}