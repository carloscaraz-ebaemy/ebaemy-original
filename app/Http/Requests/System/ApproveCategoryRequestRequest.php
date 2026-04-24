<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Al aprobar una marketplace_category_request, el SuperAdmin puede:
 *   - Aceptar el suggested_name tal cual (lo más común) → crea categoría
 *   - Renombrarla antes de crear (override_name)
 *   - Decidir bajo qué padre va (override_parent_id)
 *   - Agregar un mensaje al seller
 */
class ApproveCategoryRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'override_name'      => 'nullable|string|max:120',
            'override_parent_id' => 'nullable|integer|exists:marketplace_categories,id',
            'admin_response'     => 'nullable|string|max:2000',
        ];
    }
}
