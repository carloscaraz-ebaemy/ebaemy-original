<?php

namespace Tests\Unit;

use App\Models\Tenant\Item;
use App\Models\Tenant\ItemVariant;
use App\Models\Tenant\PricingSettings;
use App\Rules\MinMarginRule;
use Tests\TestCase;

/**
 * El guardarraíl de precio aplicado a una VARIANTE.
 *
 * El precio del producto padre pasaba por MinMarginRule desde 2026-05; el de
 * la variante no se validaba en absoluto —'numeric|min:0' y nada más— así que
 * con la política de bloqueo activa se podía dejar la talla 45 por debajo de
 * su costo por una puerta que el guardarraíl no vigilaba.
 *
 * Lo que se protege aquí es la herencia, que es donde está la sutileza: una
 * variante toma su costo propio si lo tiene y el del padre si no, pero la
 * POLÍTICA (margen mínimo, modo liquidación) es siempre del producto, porque
 * es una decisión de negocio sobre el producto y no sobre cada talla.
 *
 * Sin base de datos salvo PricingSettings, que la regla lee para saber si el
 * tenant bloquea o solo advierte.
 */
class VariantMarginGuardrailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.tenant' => config('database.connections.system')]);

        // La regla consulta la política del tenant. La inyectamos en memoria
        // para no depender de la fila real ni tocar ninguna base.
        $this->settings = new PricingSettings();
        $this->settings->id                     = 1;
        $this->settings->default_min_margin_pct = 0;
        $this->settings->block_sales_below_cost = true;
    }

    private PricingSettings $settings;

    private function item(array $attrs = []): Item
    {
        $item = new Item();
        $item->id                    = 1;
        $item->purchase_unit_price   = $attrs['cost']        ?? 150;
        $item->sale_unit_price       = $attrs['price']       ?? 300;
        $item->landed_cost_extra_pct = $attrs['extra_pct']   ?? 0;
        $item->min_margin_pct        = $attrs['min_margin']  ?? null;
        $item->liquidation_mode      = $attrs['liquidation'] ?? false;

        return $item;
    }

    private function variant(array $attrs = []): ItemVariant
    {
        $variant = new ItemVariant();
        $variant->id                  = 7;
        $variant->item_id             = 1;
        $variant->display_name        = 'Negro / 40';
        $variant->sale_unit_price     = $attrs['price'] ?? null;
        $variant->purchase_unit_price = $attrs['cost']  ?? null;

        return $variant;
    }

    public function test_una_variante_sin_costo_propio_hereda_el_del_producto(): void
    {
        $rule = MinMarginRule::forVariant($this->variant(), $this->item(['cost' => 150]), null, $this->settings);

        // 140 está por debajo del costo heredado de 150.
        $this->assertFalse($rule->passes('sale_unit_price', 140));
        $this->assertStringContainsString('150.00', $rule->message());
    }

    public function test_el_costo_propio_de_la_variante_gana_sobre_el_del_producto(): void
    {
        // El producto cuesta 150 pero esta talla cuesta 200: 180 es pérdida
        // aunque supere el costo del padre.
        $rule = MinMarginRule::forVariant(
            $this->variant(['cost' => 200]),
            $this->item(['cost' => 150]),
            null,
            $this->settings
        );

        $this->assertFalse($rule->passes('sale_unit_price', 180));
    }

    public function test_el_costo_que_se_esta_guardando_ahora_gana_sobre_el_ya_registrado(): void
    {
        // Precio y costo pueden venir en la misma petición. Validar contra el
        // costo viejo dejaría pasar la combinación nueva.
        $rule = MinMarginRule::forVariant(
            $this->variant(['cost' => 100]),
            $this->item(['cost' => 150]),
            250.0, // el costo que trae este PATCH
            $this->settings
        );

        $this->assertFalse($rule->passes('sale_unit_price', 200));
    }

    public function test_el_costo_adicional_del_producto_se_aplica_a_la_variante(): void
    {
        // Costo 150 + 20 % de flete = 180 efectivo. 170 es pérdida.
        $rule = MinMarginRule::forVariant(
            $this->variant(),
            $this->item(['cost' => 150, 'extra_pct' => 20]),
            null,
            $this->settings
        );

        $this->assertFalse($rule->passes('sale_unit_price', 170));
        $this->assertTrue($rule->passes('sale_unit_price', 190));
    }

    public function test_el_modo_liquidacion_del_producto_permite_vender_bajo_costo(): void
    {
        $rule = MinMarginRule::forVariant(
            $this->variant(),
            $this->item(['cost' => 150, 'liquidation' => true]),
            null,
            $this->settings
        );

        $this->assertTrue($rule->passes('sale_unit_price', 100));
    }

    public function test_el_margen_minimo_del_producto_rige_para_sus_variantes(): void
    {
        // Costo 150 con margen mínimo del 40 % → piso = 150 / 0.6 = 250.
        $rule = MinMarginRule::forVariant(
            $this->variant(),
            $this->item(['cost' => 150, 'min_margin' => 40]),
            null,
            $this->settings
        );

        $this->assertFalse($rule->passes('sale_unit_price', 240));
        $this->assertStringContainsString('250.00', $rule->message());
        $this->assertTrue($rule->passes('sale_unit_price', 260));
    }

    public function test_un_precio_sano_pasa(): void
    {
        // El caso del informe: costo 150, precio 300 → 50 % de margen.
        $rule = MinMarginRule::forVariant($this->variant(), $this->item(), null, $this->settings);

        $this->assertTrue($rule->passes('sale_unit_price', 300));
    }
}
