<?php

namespace Tests\Unit;

use App\Services\Tenant\StoreShipping;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Las reglas del formulario «¿Cómo envías tus productos?».
 *
 * El caso que lo motivó: alasitas eligió más de 6 agencias, el servidor
 * tenía un tope de 6 que el selector no conocía, y el vendedor solo veía
 * «No se pudo guardar».
 */
class StoreShippingRulesTest extends TestCase
{
    private function datos(array $cambios = []): array
    {
        return array_merge([
            'lima_mode' => 'quote', 'lima_cost' => null, 'lima_days_min' => 1, 'lima_days_max' => 2,
            'province_mode' => 'destination', 'province_cost' => null, 'province_handling_days' => 4,
            'province_transit_min' => 2, 'province_transit_max' => 5,
            'province_agencies' => ['Shalom', 'Marvisur', 'Cruz del Sur Cargo'],
            'agency_fee_mode' => 'amount', 'agency_fee' => 20,
            'pickup_enabled' => true, 'pickup_district' => 'Jiron Cuzco 626', 'pickup_ready_hours' => 24,
            'free_from' => 0, 'cutoff_time' => '10:00',
        ], $cambios);
    }

    private function errores(array $datos): array
    {
        return Validator::make($datos, StoreShipping::rules())->errors()->toArray();
    }

    /** @test */
    public function la_configuracion_de_la_captura_de_alasitas_es_valida()
    {
        $this->assertSame([], $this->errores($this->datos()));
    }

    /** @test */
    public function siete_agencias_ya_no_se_rechazan()
    {
        $siete = ['Shalom', 'Olva Courier', 'Marvisur', 'Cruz del Sur Cargo', 'Civa', 'Ittsa', 'Oltursa'];

        $this->assertSame([], $this->errores($this->datos(['province_agencies' => $siete])));
    }

    /** @test */
    public function el_tope_es_el_del_selector()
    {
        $demasiadas = array_map(fn ($i) => 'Agencia ' . $i, range(1, StoreShipping::MAX_AGENCIES + 1));

        $this->assertArrayHasKey('province_agencies', $this->errores($this->datos(['province_agencies' => $demasiadas])));
        $this->assertSame(12, StoreShipping::MAX_AGENCIES, 'Si cambia, cambiar tambien :multiple-limit en StoreShippingSettings.vue');
    }
}
