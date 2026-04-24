<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

class RequestDocumentsSellerApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'documents_requested' => 'required|string|min:10|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'documents_requested.required' => 'Describe qué documentos necesitas del seller.',
            'documents_requested.min'      => 'La descripción debe tener al menos 10 caracteres.',
        ];
    }
}
