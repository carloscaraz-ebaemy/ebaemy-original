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
        return [
            'plan_id'    => 'required|integer|exists:plans,id',
            'type'       => 'nullable|string|in:admin,integrator',
            'modules'    => 'nullable|array',
            'modules.*'  => 'integer',
            'levels'     => 'nullable|array',
            'levels.*'   => 'integer',
        ];
    }
}
