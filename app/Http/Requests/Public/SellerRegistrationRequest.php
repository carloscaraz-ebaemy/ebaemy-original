<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación del formulario público de pre-registro de sellers.
 *
 * Las reglas de unicidad (subdominio/RUC/email en seller_applications
 * activas + websites + clients) se delegan al SellerApplicationService
 * para poder consultarse contra la conexión correcta (system). Aquí
 * solo se valida formato, longitud y reserved subdomains.
 */
class SellerRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // endpoint público
    }

    public function rules(): array
    {
        $excluded = array_map('strtolower', config('tenant.excluded_subdomains', []));

        return [
            // ── Empresa ─────────────────────────────────────────
            'ruc'                           => 'required|digits:11|regex:/^(10|15|17|20)\d{9}$/',
            'business_name'                 => 'required|string|max:255',
            'trade_name'                    => 'nullable|string|max:255',
            'category_id'                   => 'nullable|integer',
            'fiscal_address'                => 'nullable|string|max:500',
            'department_id'                 => 'nullable|string|max:10',
            'province_id'                   => 'nullable|string|max:10',
            'district_id'                   => 'nullable|string|max:10',

            // ── Responsable legal ───────────────────────────────
            'legal_representative_name'     => 'required|string|max:180',
            'legal_representative_dni'      => 'required|digits:8',
            'legal_representative_position' => 'nullable|string|max:100',
            'email'                         => 'required|email|max:180',
            'phone'                         => 'required|string|max:30',

            // ── Tienda y acceso ─────────────────────────────────
            'requested_subdomain' => [
                'required', 'string', 'min:3', 'max:60',
                'regex:/^[a-z0-9](?:[a-z0-9-]{1,58}[a-z0-9])?$/',
                Rule::notIn($excluded),
            ],
            'store_name'        => 'nullable|string|max:180',
            'store_description' => 'nullable|string|max:2000',
            'password'          => [
                'required', 'string', 'min:8', 'confirmed',
                // Fortaleza: al menos una minúscula, una mayúscula y un número.
                // No forzamos símbolo para no bloquear sellers con teclados latam
                // que tienen fricción con caracteres especiales.
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
            ],

            // ── Opcionales ──────────────────────────────────────
            'facebook_url'  => 'nullable|url|max:500',
            'instagram_url' => 'nullable|url|max:500',
            'tiktok_url'    => 'nullable|url|max:500',
            'website_url'   => 'nullable|url|max:500',
            'logo_path'     => 'nullable|string|max:500',

            // ── Términos ────────────────────────────────────────
            'terms_accepted' => 'accepted',

            // ── Opt-in marketing (opcional) ─────────────────────
            'accepts_marketing' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'ruc.regex'                        => 'El RUC debe empezar con 10, 15, 17 o 20.',
            'ruc.digits'                       => 'El RUC debe tener 11 dígitos.',
            'requested_subdomain.regex'        => 'El subdominio solo acepta letras minúsculas, números y guiones (no al inicio ni al final).',
            'requested_subdomain.not_in'       => 'Ese subdominio está reservado, elige otro.',
            'legal_representative_dni.digits'  => 'El DNI debe tener exactamente 8 dígitos.',
            'password.confirmed'               => 'La confirmación de contraseña no coincide.',
            'password.min'                     => 'La contraseña debe tener al menos 8 caracteres.',
            'password.regex'                   => 'La contraseña debe incluir al menos una minúscula, una mayúscula y un número.',
            'terms_accepted.accepted'          => 'Debes aceptar los términos para continuar.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('requested_subdomain')) {
            $this->merge([
                'requested_subdomain' => strtolower(trim($this->input('requested_subdomain'))),
            ]);
        }
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->input('email'))),
            ]);
        }
    }
}
