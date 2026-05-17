<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Edición de campos de contacto + tienda de una SellerApplication antes
 * de aprobarla. Permite al SuperAdmin corregir datos que el seller envió
 * mal sin tener que rechazar la solicitud y pedirle que la rehaga.
 *
 * Alcance: solo contacto (email, phone) + tienda (subdominio, nombre,
 * descripción, redes sociales). RUC, razón social, dirección fiscal y
 * datos del responsable legal NO son editables — vienen de SUNAT o
 * tienen validez legal.
 */
class UpdateSellerApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            // Contacto
            'email' => 'required|email|max:180',
            'phone' => 'required|string|max:30',

            // Tienda
            'requested_subdomain' => [
                'required', 'string', 'min:3', 'max:60',
                'regex:/^[a-z0-9](?:[a-z0-9-]{1,58}[a-z0-9])?$/',
            ],
            'store_name'        => 'nullable|string|max:120',
            'store_description' => 'nullable|string|max:2000',

            // Redes (todos opcionales)
            'facebook_url'  => 'nullable|url|max:255',
            'instagram_url' => 'nullable|url|max:255',
            'tiktok_url'    => 'nullable|url|max:255',
            'website_url'   => 'nullable|url|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'requested_subdomain.regex' => 'Subdominio inválido. Solo minúsculas, números y guiones (no al inicio ni al final).',
            'requested_subdomain.min'   => 'El subdominio debe tener al menos 3 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'requested_subdomain' => strtolower(trim((string) $this->input('requested_subdomain'))),
        ]);
    }
}
