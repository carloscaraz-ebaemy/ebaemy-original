<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Enums\DeliveryTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ecommerce público — la auth se maneja por token de cliente
    }

    public function rules(): array
    {
        return [
            // Datos del cliente (puede ser guest)
            'customer_id'          => ['nullable', 'integer'],
            'recipient_name'       => ['required', 'string', 'max:150'],
            'recipient_phone'      => ['required', 'string', 'max:20'],
            'recipient_email'      => ['nullable', 'email'],

            // Destino
            'destination_district' => ['required', 'string', 'max:100'],
            'destination_address'  => ['required', 'string', 'max:255'],
            'destination_ubigeo'   => ['nullable', 'string', 'max:10'],

            // Almacén origen
            'warehouse_id'         => ['required', 'integer'],

            // Tipo de entrega
            'delivery_type'        => ['required', new Enum(DeliveryTypeEnum::class)],

            // Carrito
            'items'                => ['required', 'array', 'min:1'],
            'items.*.item_id'      => ['required', 'integer', 'exists:items,id'],
            'items.*.quantity'     => ['required', 'numeric', 'min:0.0001'],

            // Moneda
            'currency_type_id'     => ['nullable', 'string', 'size:3'],

            // Notas
            'notes'                => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'           => 'El carrito no puede estar vacío.',
            'items.*.item_id.exists'   => 'Uno o más productos no existen.',
            'items.*.quantity.min'     => 'La cantidad debe ser mayor a 0.',
            'destination_address.required' => 'La dirección de destino es obligatoria.',
            'recipient_name.required'  => 'El nombre del destinatario es obligatorio.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency_type_id' => $this->currency_type_id ?? 'PEN',
            'source'           => 'ecommerce',
        ]);
    }
}
