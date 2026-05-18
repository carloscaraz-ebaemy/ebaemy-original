<?php

namespace App\Http\Requests\Tenant;

use App\Rules\MinMarginRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Class ItemRequest
 *
 * @package App\Http\Requests\Tenant
 * @mixin FormRequest
 */
class ItemRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->input('id');

        $cost              = (float) ($this->input('purchase_unit_price') ?? 0);
        $landedExtraPct    = (float) ($this->input('landed_cost_extra_pct') ?? 0);
        $minMarginPct      = $this->input('min_margin_pct');
        $minMarginPct      = $minMarginPct === null || $minMarginPct === '' ? null : (float) $minMarginPct;
        $liquidationMode   = (bool) $this->input('liquidation_mode', false);

        return [
            'internal_id' => [
                'nullable',
                Rule::unique('tenant.items')->ignore($id),
            ],
            'description' => [
                'required', 'max:600'
            ],
            'name' => [
                'max:600'
            ],
            'second_name' => [
                'max:600'
            ],
            'unit_type_id' => [
                'required',
            ],
            'currency_type_id' => [
                'required'
            ],
            'sale_unit_price' => [
                'required',
                'numeric',
                'gt:0',
                // Guardrail: bloquea venta bajo costo (salvo liquidation_mode)
                // y advierte si rompe min_margin_pct. Ver [[project_pricing_redesign]].
                new MinMarginRule($cost, $landedExtraPct, $minMarginPct, $liquidationMode),
            ],
            'purchase_unit_price' => [
                'required', 'numeric', 'gte:0'
            ],
            'landed_cost_extra_pct' => [
                'nullable', 'numeric', 'between:0,100',
            ],
            'target_margin_pct' => [
                'nullable', 'numeric', 'between:0,99.99',
            ],
            'min_margin_pct' => [
                'nullable', 'numeric', 'between:0,99.99', 'lte:target_margin_pct',
            ],
            'compare_at_price' => [
                'nullable', 'numeric', 'gte:sale_unit_price',
            ],
            'pricing_mode' => [
                'nullable', 'in:margin,markup,manual',
            ],
            'liquidation_mode' => [
                'nullable', 'boolean',
            ],
            'stock' => [
                'required',
            ],
            'stock_min' => [
                'required',
            ],
            'sale_affectation_igv_type_id' => [
                'required'
            ],
            'purchase_affectation_igv_type_id' => [
                'required'
            ],
            'model' => 'max:100',

            'system_isc_type_id' => [
                'required_if:has_isc, 1',
            ],
            'percentage_isc' => [
                'required_if:has_isc, 1',
                'numeric',
                'min:0',
            ],

            'purchase_system_isc_type_id' => [
                'required_if:purchase_has_isc, 1',
            ],
            'purchase_percentage_isc' => [
                'required_if:purchase_has_isc, 1',
                'numeric',
            ],
        ];
    }

    public function messages()
    {
        return [
            'description.required'         => 'El campo nombre es obligatorio.',
            'name.max'                     => 'La descripción debe ser inferior a 600 caracteres.',
            'sale_unit_price.gt'           => 'El precio unitario de venta debe ser mayor que 0.',
            'compare_at_price.gte'         => 'El precio tachado debe ser mayor o igual al precio de venta.',
            'min_margin_pct.lte'           => 'El margen mínimo no puede ser mayor que el margen objetivo.',
            'landed_cost_extra_pct.between'=> 'El % de costo adicional debe estar entre 0 y 100.',
            'target_margin_pct.between'    => 'El margen objetivo debe ser menor a 100%.',
            'min_margin_pct.between'       => 'El margen mínimo debe ser menor a 100%.',
        ];
    }
}
