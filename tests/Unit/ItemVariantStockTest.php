<?php

namespace Tests\Unit;

use App\Models\Tenant\Item;
use App\Services\Tenant\ItemVariantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Cuánto stock tiene un producto con variantes.
 *
 * Antes había cuatro respuestas para esta pregunta —propagateStock contaba
 * todas las variantes, stock:sync-variants solo las activas, stock:reconcile
 * restaba lo comprometido, y el sync del marketplace leía la columna legacy—
 * y se sobrescribían entre sí: el comando de reconciliación corregía un valor
 * que el siguiente guardado del producto volvía a cambiar.
 *
 * Lo que se protege aquí son las dos decisiones de negocio que zanjaron el
 * empate, porque son las que determinan qué ve el comprador:
 *
 *   1. El stock de una variante DESACTIVADA no cuenta. Es una combinación que
 *      el negocio retiró; contarla publica disponibilidad que no existe.
 *   2. Lo que se publica es DISPONIBLE (físico − comprometido). Publicar el
 *      físico vende la última unidad que ya estaba apartada para otro pedido.
 *
 * Sobre SQLite en memoria: computeStock() es una sola consulta agregada y no
 * usa nada específico de MySQL, así que el test corre sin tocar los 11 tenants.
 */
class ItemVariantStockTest extends TestCase
{
    private ItemVariantService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Este PHP de desarrollo trae pdo_mysql pero no pdo_sqlite. En vez de
        // pedir que se toque el php.ini para correr la suite, el test se salta
        // solo: en CI —donde sqlite sí está— corre con normalidad.
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

        $schema->create('item_variants', function ($t) {
            $t->integer('id')->primary();
            $t->integer('item_id');
            $t->boolean('is_active')->default(true);
        });

        $schema->create('item_variant_warehouse', function ($t) {
            $t->increments('id');
            $t->integer('item_variant_id');
            $t->integer('warehouse_id');
            $t->decimal('stock_physical', 12, 4)->default(0);
            $t->decimal('stock_committed', 12, 4)->default(0);
        });

        $this->service = new ItemVariantService();
    }

    /** Crea una variante con sus filas de stock por almacén. */
    private function variant(int $id, bool $active, array $warehouses): void
    {
        DB::connection('tenant')->table('item_variants')->insert([
            'id' => $id, 'item_id' => 1, 'is_active' => $active,
        ]);

        foreach ($warehouses as $warehouseId => [$physical, $committed]) {
            DB::connection('tenant')->table('item_variant_warehouse')->insert([
                'item_variant_id' => $id,
                'warehouse_id'    => $warehouseId,
                'stock_physical'  => $physical,
                'stock_committed' => $committed,
            ]);
        }
    }

    private function item(): Item
    {
        $item = new Item();
        $item->id = 1;

        return $item;
    }

    public function test_el_stock_de_una_variante_desactivada_no_cuenta(): void
    {
        $this->variant(1, true,  [1 => [10, 0]]);
        $this->variant(2, false, [1 => [5,  0]]);

        $stock = $this->service->computeStock($this->item());

        $this->assertSame(10.0, $stock['physical']);
    }

    public function test_se_pueden_pedir_las_desactivadas_explicitamente(): void
    {
        $this->variant(1, true,  [1 => [10, 0]]);
        $this->variant(2, false, [1 => [5,  0]]);

        $stock = $this->service->computeStock($this->item(), null, true);

        $this->assertSame(15.0, $stock['physical']);
    }

    public function test_lo_publicable_descuenta_lo_comprometido(): void
    {
        $this->variant(1, true, [1 => [10, 3]]);

        $this->assertSame(7.0,  $this->service->publishableStock($this->item()));
        // El físico sigue siendo 10: lo comprometido no desaparece del almacén,
        // solo deja de ofrecerse.
        $this->assertSame(10.0, $this->service->computeStock($this->item())['physical']);
    }

    public function test_lo_comprometido_de_una_variante_desactivada_tampoco_cuenta(): void
    {
        $this->variant(1, true,  [1 => [10, 2]]);
        $this->variant(2, false, [1 => [50, 40]]);

        $stock = $this->service->computeStock($this->item());

        $this->assertSame(10.0, $stock['physical']);
        $this->assertSame(2.0,  $stock['committed']);
    }

    public function test_el_disponible_nunca_es_negativo(): void
    {
        // Pasa de verdad: un ajuste manual a la baja puede dejar el físico por
        // debajo de lo ya comprometido. Eso es cero disponible, no una deuda.
        $this->variant(1, true, [1 => [2, 5]]);

        $stock = $this->service->computeStock($this->item(), null, false, true);

        $this->assertSame(0.0, $stock['total']);
        $this->assertSame(0.0, $stock['by_warehouse'][1]['available']);
    }

    public function test_desglosa_por_almacen_y_permite_acotar_a_uno(): void
    {
        $this->variant(1, true, [1 => [10, 2], 2 => [7, 0]]);

        $todos = $this->service->computeStock($this->item());
        $this->assertSame(17.0, $todos['physical']);
        $this->assertSame(8.0,  $todos['by_warehouse'][1]['available']);
        $this->assertSame(7.0,  $todos['by_warehouse'][2]['available']);

        $uno = $this->service->computeStock($this->item(), 2);
        $this->assertSame(7.0, $uno['physical']);
        $this->assertArrayNotHasKey(1, $uno['by_warehouse']);
    }

    public function test_un_producto_sin_filas_de_stock_da_cero_y_no_revienta(): void
    {
        $stock = $this->service->computeStock($this->item());

        $this->assertSame(0.0, $stock['total']);
        $this->assertSame([],  $stock['by_warehouse']);
    }
}
