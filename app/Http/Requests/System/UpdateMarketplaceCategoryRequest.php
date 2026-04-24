<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMarketplaceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'name'                      => 'sometimes|required|string|max:120',
            'slug'                      => 'sometimes|nullable|string|max:80|regex:/^[a-z0-9](?:[a-z0-9-]{0,78}[a-z0-9])?$/',
            'parent_id'                 => 'sometimes|nullable|integer|exists:marketplace_categories,id',
            'icon'                      => 'sometimes|nullable|string|max:80',
            'image'                     => 'sometimes|nullable|string|max:500',
            'description'               => 'sometimes|nullable|string|max:2000',
            'is_active'                 => 'sometimes|boolean',
            'is_visible_in_marketplace' => 'sometimes|boolean',
            'allow_seller_publish'      => 'sometimes|boolean',
            'sort_order'                => 'sometimes|integer|min:0',
        ];
    }
}
