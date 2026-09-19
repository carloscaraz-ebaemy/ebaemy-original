<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigurationEcommerceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->input('id');
        return [
            'information_contact_email' => [
                'required',
            ],
            'information_contact_name' => [
                'required',
            ],
            'information_contact_phone' => [
                'required',
            ],
            'information_contact_address' => [
                'required',
            ],
            // Opcional: si está vacío, el Libro de Reclamaciones cae al email
            // de contacto. Lo que no puede es ser una dirección inválida.
            'claims_email' => [
                'nullable',
                'email',
                'max:120',
            ],
        ];
    }

    public function messages()
    {
        return [
        'information_contact_email.required' => 'El campo Email de motivo de traslado es obligatorio.',
        'information_contact_name.required' => 'El campo Nombre es obligatorio.',
        'information_contact_phone.required' => 'El campo Telefono es obligatorio.',
        'information_contact_address.required' => 'El campo Dirección es obligatorio.',
        'claims_email.email' => 'El correo del Libro de Reclamaciones no es válido.',

        ];
    }
}
