<?php

namespace Tests\Unit;

use App\Models\Tenant\Item;
use App\Services\Tenant\ItemVariantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Dónde cae un movimiento de stock de un producto con variantes.
 *
 * El problema que protege este test: `item_warehouse.stock` e `items.stock` son
 * valores DERIVADOS cuando `has_variants = true`, porque
 * `ItemVariantService::propagateStock()` los reescribe enteros desde
 * `item_variant_warehouse`. Los traits de kardex e inventario los escribían
 * directamente, así que el descuento de una venta se perdía en la siguiente
 * propagación —y la propagación ocurre al guardar el producto, al editar
 * cualquier variante y al correr `stock:reconcile`—. El stock vendido
 * reaparecía solo.
 *
 * `applyStockDelta()` es el único punto por el que esos traits pueden mover
 * stock de un producto con variantes. Lo que se fija aquí es su contrato:
 *
 *   1. Un producto SIN variantes no es asunto suyo: devuelve false y el
 *      llamador sigue con su camino legacy, que para un producto simple es
 *      correcto.
 *   2. Con la variante en la línea, el delta cae en ESA variante.
 *   3. Sin variante en la línea —`document_items` y compañía no tienen la
 *      columna— cae en la `is_primary`. No es correcto, es lo que mantiene el
 *      TOTAL del producto bien mientras la línea no la lleve.
 *   4. El almacén del movimiento se respeta: un delta del almacén 2 no sale
 *      del almacén 1 porque el 1 tenga más stock.
 *   5. No deja la variante en negativo, y deja constancia del faltante.
 *
 * Sobre SQLite en memoria: igual que ItemVariantStockTest, el servicio no usa
 * nada específico de MySQL en este camino, así que el test corre sin tocar
 * ningún tenant.
 */
class VariantStockDeltaTest extends TestCase
{
    private ItemVariantService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Mismo motivo que en ItemVariantStockTest: este PHP de desarrollo trae
        // pdo_mysql pero no pdo_sqlite. En CI sí está y el test corre.
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite no está habilitado en este PHP.');
        }

        config(['database.connections.tenant' => [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]]);
        DB::purge('tenant');

        $schema = Schema::connection('tenant');

        $schema->create('items', function ($t) {
            $t->integer('id')->primary();
            $t->boolean('has_variants')->default(false);
            $t->decimal('stock', 16, 4)->default(0);
            $t->integer('warehouse_id')->nullable();
            // El modelo Item rellena estas dos al guardar (hook de `saving`),
            // asi que sin ellas cualquier save() del padre revienta.
            $t->string('text_filter')->nullable();
            $t->string('slug')->nullable();
            $t->timestamps();
        });

        $schema->create('item_variants', function ($t) {
            $t->integer('id')->primary();
            $t->integer('item_id');
            $t->boolean('is_active')->default(true);
            $t->boolean('is_primary')->default(false);
            $t->decimal('stock', 12, 4)->default(0);
            $t->timestamps();
        });

        $schema->create('item_variant_warehouse', function ($t) {
            $t->increments('id');
            $t->integer('item_variant_id');
            $t->integer('warehouse_id');
            $t->decimal('stock', 12, 4)->default(0);
            $t->decimal('stock_physical', 12, 4)->default(0);
            $t->decimal('stock_committed', 12, 4)->default(0);
            $t->timestamps();
        });

        $schema->create('item_warehouse', function ($t) {
            $t->increments('id');
            $t->integer('item_id');
            $t->integer('warehouse_id');
            $t->decimal('stock', 12, 4)->default(0);
            $t->decimal('stock_physical', 12, 4)->default(0);
            $t->decimal('stock_committed', 12, 4)->default(0);
            $t->timestamps();
        });

        $this->service = new ItemVariantService();
    }

    private function item(bool $hasVariants = true): Item
    {
        DB::connection('tenant')->table('items')->insert([
            'id' => 1, 'has_variants' => $hasVariants, 'stock' => 0, 'warehouse_id' => 1,
        ]);

        return Item::on('tenant')->find(1);
    }

    /** @param array<int, array{0: float, 1: float}> $warehouses  [warehouse_id => [physical, committed]] */
    private function variant(int $id, array $warehouses, bool $primary = false, bool $active = true): void
    {
        DB::connection('tenant')->table('item_variants')->insert([
            'id'         => $id,
            'item_id'    => 1,
            'is_active'  => $active,
            'is_primary' => $primary,
            'stock'      => array_sum(array_column($warehouses, 0)),
        ]);

        foreach ($warehouses as $warehouseId => [$physical, $committed]) {
            DB::connection('tenant')->table('item_variant_warehouse')->insert([
                'item_variant_id' => $id,
                'warehouse_id'    => $warehouseId,
                'stock'           => $physical,
                'stock_physical'  => $physical,
                'stock_committed' => $committed,
            ]);
        }
    }

    private function fisico(int $variantId, int $warehouseId): float
    {
        return (float) DB::connection('tenant')->table('item_variant_warehouse')
            ->where('item_variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->value('stock_physical');
    }

    public function test_un_producto_sin_variantes_no_es_asunto_suyo(): void
    {
        $item = $this->item(false);

        $this->assertFalse(
            $this->service->applyStockDelta($item, -3, 1, null, 'test'),
            'Con has_variants=false debe devolver false para que el llamador siga con su camino legacy.'
        );
    }

    public function test_con_la_variante_en_la_linea_el_delta_cae_en_esa_variante(): void
    {
        $item = $this->item();
        $this->variant(1, [1 => [10, 0]], primary: true);
        $this->variant(2, [1 => [8,  0]]);

        $this->assertTrue($this->service->applyStockDelta($item, -3, 1, 2, 'venta'));

        $this->assertSame(10.0, $this->fisico(1, 1), 'La principal no se toca si la línea dice otra variante.');
        $this->assertSame(5.0,  $this->fisico(2, 1));
    }

    public function test_sin_variante_en_la_linea_el_delta_cae_en_la_principal(): void
    {
        $item = $this->item();
        $this->variant(1, [1 => [10, 0]], primary: true);
        $this->variant(2, [1 => [8,  0]]);

        $this->assertTrue($this->service->applyStockDelta($item, -4, 1, null, 'venta sin variante'));

        $this->assertSame(6.0, $this->fisico(1, 1));
        $this->assertSame(8.0, $this->fisico(2, 1));
    }

    public function test_sin_principal_marcada_cae_en_la_activa_con_mas_stock(): void
    {
        $item = $this->item();
        $this->variant(1, [1 => [2,  0]]);
        $this->variant(2, [1 => [20, 0]]);

        $this->assertTrue($this->service->applyStockDelta($item, -5, 1, null, 'venta'));

        $this->assertSame(2.0,  $this->fisico(1, 1));
        $this->assertSame(15.0, $this->fisico(2, 1));
    }

    public function test_una_variante_desactivada_nunca_recibe_el_movimiento(): void
    {
        $item = $this->item();
        $this->variant(1, [1 => [100, 0]], primary: false, active: false);
        $this->variant(2, [1 => [4,   0]]);

        $this->assertTrue($this->service->applyStockDelta($item, -1, 1, null, 'venta'));

        $this->assertSame(100.0, $this->fisico(1, 1), 'La desactivada guarda stock retirado; no se vende desde ahí.');
        $this->assertSame(3.0,   $this->fisico(2, 1));
    }

    public function test_se_respeta_el_almacen_del_movimiento(): void
    {
        $item = $this->item();
        // El almacén 1 tiene mucho más stock: si se eligiera por "el que más
        // tiene", el movimiento del almacén 2 saldría del equivocado.
        $this->variant(1, [1 => [50, 0], 2 => [6, 0]], primary: true);

        $this->assertTrue($this->service->applyStockDelta($item, -2, 2, null, 'venta en almacen 2'));

        $this->assertSame(50.0, $this->fisico(1, 1));
        $this->assertSame(4.0,  $this->fisico(1, 2));
    }

    public function test_un_ingreso_suma(): void
    {
        $item = $this->item();
        $this->variant(1, [1 => [5, 0]], primary: true);

        $this->assertTrue($this->service->applyStockDelta($item, 7, 1, 1, 'nota de credito'));

        $this->assertSame(12.0, $this->fisico(1, 1));
    }

    public function test_no_deja_la_variante_en_negativo(): void
    {
        $item = $this->item();
        $this->variant(1, [1 => [3, 0]], primary: true);

        $this->assertTrue($this->service->applyStockDelta($item, -10, 1, 1, 'venta sin stock'));

        $this->assertSame(0.0, $this->fisico(1, 1));
    }

    public function test_el_total_del_producto_queda_derivado_de_las_variantes(): void
    {
        $item = $this->item();
        $this->variant(1, [1 => [10, 0]], primary: true);
        $this->variant(2, [1 => [5,  0]]);

        $this->service->applyStockDelta($item, -4, 1, 1, 'venta');

        // 6 + 5: propagateStock() reconstruye el padre desde las variantes, que
        // es exactamente lo que hacía desaparecer el descuento cuando la venta
        // escribía el nivel derivado.
        $this->assertSame(11.0, (float) DB::connection('tenant')->table('items')->where('id', 1)->value('stock'));
        $this->assertSame(11.0, (float) DB::connection('tenant')->table('item_warehouse')
            ->where('item_id', 1)->where('warehouse_id', 1)->value('stock_physical'));
    }

    public function test_la_cache_de_la_variante_se_actualiza(): void
    {
        $item = $this->item();
        $this->variant(1, [1 => [10, 0], 2 => [4, 0]], primary: true);

        $this->service->applyStockDelta($item, -3, 1, 1, 'venta');

        $this->assertSame(11.0, (float) DB::connection('tenant')->table('item_variants')->where('id', 1)->value('stock'));
    }

    public function test_un_producto_con_has_variants_y_sin_variantes_activas_no_revienta(): void
    {
        $item = $this->item();
        $this->variant(1, [1 => [10, 0]], primary: false, active: false);

        $this->assertTrue(
            $this->service->applyStockDelta($item, -5, 1, null, 'venta'),
            'Devuelve true igual: el llamador NO debe caer al camino legacy y escribir el derivado.'
        );

        $this->assertSame(10.0, $this->fisico(1, 1));
    }

    public function test_un_delta_cero_no_hace_nada_pero_reclama_la_linea(): void
    {
        $item = $this->item();
        $this->variant(1, [1 => [10, 0]], primary: true);

        $this->assertTrue($this->service->applyStockDelta($item, 0, 1, 1, 'ajuste vacio'));
        $this->assertSame(10.0, $this->fisico(1, 1));
    }
}
