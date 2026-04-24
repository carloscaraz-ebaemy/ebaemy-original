<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

class RejectSellerApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => 'required|string|min:10|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Debes indicar un motivo de rechazo.',
            'rejection_reason.min'      => 'El motivo debe tener al menos 10 caracteres.',
        ];
    }
}
