<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación del form público de solicitud de activación de tienda virtual
 * para tenants que YA son clientes pero no tienen marketplace_enabled.
 *
 * A diferencia de SellerRegistrationRequest (onboarding nuevo), este form
 * NO pide subdominio ni contraseña — el tenant ya existe con sus
 * credenciales y URL. Solo recolecta datos de contacto del solicitante
 * para que el SuperAdmin pueda validar que es el dueño real del tenant
 * antes de activar la tienda virtual.
 */
class SellerActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // endpoint público
    }

    public function rules(): array
    {
        return [
            // Empresa — el RUC debe coincidir con un client existente sin
            // marketplace_enabled. La validación de existencia la hace el
            // service (findExistingRegistration) porque requiere conexión
            // system, no es viable en reglas declarativas.
            'ruc' => 'required|digits:11|regex:/^(10|15|17|20)\d{9}$/',

            // Responsable legal
            'legal_representative_name'     => 'required|string|max:180',
            'legal_representative_dni'      => 'nullable|digits:8',
            'legal_representative_position' => 'nullable|string|max:100',
            'email'                         => 'required|email|max:180',
            'phone'                         => 'required|string|max:30',

            // Motivo (opcional, para ayudar al SuperAdmin a priorizar)
            'activation_reason' => 'nullable|string|max:2000',

            // Términos
            'terms_accepted' => 'accepted',
        ];
    }

    public function messages(): array
    {
        return [
            'ruc.regex'               => 'El RUC debe empezar con 10, 15, 17 o 20.',
            'ruc.digits'              => 'El RUC debe tener 11 dígitos.',
            'terms_accepted.accepted' => 'Debes aceptar los términos para continuar.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }
}
