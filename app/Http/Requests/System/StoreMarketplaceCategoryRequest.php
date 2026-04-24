<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMarketplaceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'name'                      => 'required|string|max:120',
            'slug'                      => 'nullable|string|max:80|regex:/^[a-z0-9](?:[a-z0-9-]{0,78}[a-z0-9])?$/',
            'parent_id'                 => 'nullable|integer|exists:marketplace_categories,id',
            'icon'                      => 'nullable|string|max:80',
            'image'                     => 'nullable|string|max:500',
            'description'               => 'nullable|string|max:2000',
            'is_active'                 => 'nullable|boolean',
            'is_visible_in_marketplace' => 'nullable|boolean',
            'allow_seller_publish'      => 'nullable|boolean',
            'sort_order'                => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'El slug solo acepta minúsculas, números y guiones (no al inicio ni al final).',
        ];
    }
}
