<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

class RejectCategoryRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'admin_response' => 'required|string|min:10|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'admin_response.required' => 'Debes indicar al seller por qué se rechaza la solicitud.',
            'admin_response.min'      => 'El motivo debe tener al menos 10 caracteres.',
        ];
    }
}
