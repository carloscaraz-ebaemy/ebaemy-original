<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación del formulario público del Libro de Reclamaciones.
 *
 * Los mensajes están escritos uno a uno: quien llena este formulario suele
 * estar molesto y no merece un «validation.required» en la cara.
 */
class ClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'                => 'required|in:reclamo,queja',
            'item_type'           => 'required|in:bien,servicio',

            'document_type'       => 'required|in:DNI,CE,PAS,RUC',
            'document_number'     => 'required|string|max:20|regex:/^[A-Za-z0-9\-]+$/',
            'names'               => 'required|string|max:100',
            'surnames'            => 'required|string|max:100',
            'email'               => 'required|email:rfc|max:120',
            'phone'               => 'nullable|string|max:30',
            'address'             => 'required|string|max:255',
            'department_id'       => 'nullable|string|max:2',
            'province_id'         => 'nullable|string|max:4',
            'district_id'         => 'nullable|string|max:6',
            'is_minor'            => 'nullable|boolean',
            'guardian_name'       => 'required_if:is_minor,1|nullable|string|max:200',

            'order_id'            => 'nullable|integer',
            'purchase_date'       => 'nullable|date|before_or_equal:today',
            'currency'            => 'required|in:PEN,USD',
            'amount'              => 'nullable|numeric|min:0|max:9999999.99',
            'product_description' => 'required|string|min:5|max:1000',

            'detail'              => 'required|string|min:20|max:2000',
            'consumer_request'    => 'required|string|min:10|max:1000',

            'files'               => 'nullable|array|max:5',
            'files.*'             => 'file|mimes:jpg,jpeg,png,pdf|max:5120',

            'accept_terms'        => 'accepted',
            'submission_token'    => 'required|string|size:40',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'                => 'Indica si presentas un reclamo o una queja.',
            'type.in'                      => 'Indica si presentas un reclamo o una queja.',
            'item_type.required'           => 'Indica si se trata de un bien o de un servicio.',

            'document_type.required'       => 'Elige tu tipo de documento.',
            'document_number.required'     => 'Ingresa tu número de documento.',
            'document_number.regex'        => 'El número de documento sólo admite letras, números y guiones.',
            'names.required'               => 'Ingresa tus nombres.',
            'surnames.required'            => 'Ingresa tus apellidos.',
            'email.required'               => 'Ingresa tu correo electrónico: ahí te enviamos la copia y la respuesta.',
            'email.email'                  => 'Ingresa un correo electrónico válido.',
            'address.required'             => 'Ingresa tu domicilio.',
            'guardian_name.required_if'    => 'Si eres menor de edad, indica el nombre de tu padre, madre o apoderado.',

            'purchase_date.before_or_equal' => 'La fecha de compra no puede ser posterior a hoy.',
            'amount.numeric'               => 'El monto reclamado debe ser un número.',
            'product_description.required' => 'Describe el producto o servicio contratado.',
            'product_description.min'      => 'Describe el producto o servicio con un poco más de detalle.',

            'detail.required'              => 'Cuéntanos qué ocurrió.',
            'detail.min'                   => 'El detalle es demasiado corto: explícanos el problema con al menos 20 caracteres.',
            'detail.max'                   => 'El detalle no puede superar los 2000 caracteres.',
            'consumer_request.required'    => 'Indica qué solución concreta esperas.',
            'consumer_request.min'         => 'Indica con un poco más de detalle qué solución esperas.',

            'files.max'                    => 'Puedes adjuntar como máximo 5 archivos.',
            'files.*.mimes'                => 'Los archivos deben ser JPG, PNG o PDF.',
            'files.*.max'                  => 'Cada archivo debe pesar menos de 5 MB.',

            'accept_terms.accepted'        => 'Debes autorizar el tratamiento de tus datos para poder registrar el reclamo.',
            'submission_token.required'    => 'Vuelve a cargar la página e inténtalo de nuevo.',
        ];
    }

    public function attributes(): array
    {
        return [
            'names'               => 'nombres',
            'surnames'            => 'apellidos',
            'email'               => 'correo electrónico',
            'phone'               => 'teléfono',
            'address'             => 'domicilio',
            'product_description' => 'descripción',
            'detail'              => 'detalle',
            'consumer_request'    => 'pedido',
        ];
    }
}
