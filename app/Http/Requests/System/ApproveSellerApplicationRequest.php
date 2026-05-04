<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload enviado por el SuperAdmin al aprobar una solicitud.
 * Permite escoger el plan y opcionalmente módulos/niveles del usuario admin.
 */
class ApproveSellerApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        // plan_id es obligatorio SOLO para onboarding nuevo (crear tenant).
        // Para solicitudes de activación (tenant existente) no aplica — el
        // service las detecta por is_activation_request y se desvía antes
        // de necesitarlo.
        return [
            'plan_id'    => 'nullable|integer|exists:plans,id',
            'type'       => 'nullable|string|in:admin,integrator',
            'modules'    => 'nullable|array',
            'modules.*'  => 'integer',
            'levels'     => 'nullable|array',
            'levels.*'   => 'integer',
            // Override opcional: permite al SuperAdmin corregir email y/o
            // contraseña antes de crear el tenant (p.ej. seller escribió mal
            // el email, contraseña débil detectada manualmente, etc.). Si
            // no se envían, se usan los datos originales de la solicitud.
            'email_override'    => 'nullable|email|max:180',
            'password_override' => [
                'nullable', 'string', 'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
            ],
            // Permite al SuperAdmin cambiar el subdominio antes de crear el
            // tenant. Útil cuando el subdominio original quedó "en uso" por
            // un intento previo que falló a medio camino y dejó un website
            // huérfano, o cuando el seller eligió un subdominio inadecuado.
            'subdomain_override' => [
                'nullable', 'string', 'min:3', 'max:60',
                'regex:/^[a-z0-9](?:[a-z0-9-]{1,58}[a-z0-9])?$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password_override.min'    => 'La contraseña debe tener al menos 8 caracteres.',
            'password_override.regex'  => 'La contraseña debe incluir mayúscula, minúscula y número.',
            'subdomain_override.regex' => 'Subdominio inválido. Solo minúsculas, números y guiones (no al inicio ni al final).',
        ];
    }
}
