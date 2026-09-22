<?php

namespace Tests\Feature\Security;

use App\Services\Security\Alert;
use App\Services\Security\Detectors\CatalogAnomalyDetector;
use App\Services\Security\ScanContext;
use App\Services\Security\Severity;
use App\Services\Security\State\FileStateStore;
use App\Services\Security\Support\AgentConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Modulo 2 — errores de catalogo.
 *
 * Siembra un catalogo simulado con un caso de cada problema y varios productos
 * sanos que no deben aparecer en ninguna alerta.
 */
class CatalogAnomalyDetectorTest extends TestCase
{
    private const CONNECTION = 'sec_agent_sqlite';

    private string $workDir;
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Requiere pdo_sqlite: php -d extension=php_pdo_sqlite.dll vendor/phpunit/phpunit/phpunit');
        }

        $this->workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sec-agent-catalog-' . uniqid();
        mkdir($this->workDir, 0775, true);
        $this->now = CarbonImmutable::now();

        config([
            'database.connections.' . self::CONNECTION => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'database.default' => self::CONNECTION,
        ]);

        $this->createSchema();
        $this->seedCatalog();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->workDir);

        parent::tearDown();
    }

    public function test_detecta_un_caso_de_cada_problema(): void
    {
        $types = array_map(fn (Alert $a) => $a->type, $this->scan());

        foreach ([
            'precio_cero',
            'precio_bajo_costo',
            'margen_insuficiente',
            'caida_de_precio',
            'subida_de_precio',
            'stock_negativo',
            'publicado_sin_stock',
            'stock_critico',
            'precio_dispar_entre_canales',
        ] as $expected) {
            $this->assertContains($expected, $types, "Falta la deteccion de «{$expected}»");
        }
    }

    public function test_el_precio_en_cero_es_critico(): void
    {
        $alert = $this->firstOfType('precio_cero');

        $this->assertSame(Severity::CRITICA, $alert->severity);
        $this->assertSame(1, $alert->evidence['total']);
        $this->assertStringContainsString('SIN-PRECIO', $alert->evidence['productos'][0]['producto']);
    }

    public function test_usa_la_descripcion_como_nombre_del_producto(): void
    {
        // En este esquema `items.name` esta vacio: el nombre vive en `description`.
        $alert = $this->firstOfType('precio_bajo_costo');

        $this->assertStringContainsString('Zapatilla bajo costo', $alert->evidence['productos'][0]['producto']);
    }

    public function test_el_margen_se_calcula_sobre_la_venta(): void
    {
        $alert = $this->firstOfType('margen_insuficiente');

        // Precio 100, costo 95 → margen sobre venta 5%, no 5.26% (que seria markup).
        $this->assertSame('5%', $alert->evidence['productos'][0]['margen']);
        $this->assertSame(Severity::MEDIA, $alert->severity);
    }

    public function test_el_producto_con_margen_por_item_manda_sobre_el_global(): void
    {
        $alert = $this->firstOfType('margen_insuficiente');
        $nombres = array_column($alert->evidence['productos'], 'producto');

        // Margen 20% con minimo global 10% → sano, PERO su min_margin_pct es 30%.
        $this->assertTrue(
            (bool) preg_grep('/MARGEN-PROPIO/', $nombres),
            'El umbral por producto debe tener prioridad sobre el global'
        );
    }

    public function test_las_alertas_van_agrupadas_y_no_una_por_producto(): void
    {
        $alerts = array_values(array_filter($this->scan(), fn (Alert $a) => $a->type === 'stock_critico'));

        $this->assertCount(1, $alerts, 'Debe haber UNA alerta agrupada de stock critico');
        $this->assertSame(3, $alerts[0]->evidence['total']);
        $this->assertSame(Severity::BAJA, $alerts[0]->severity);
    }

    public function test_los_productos_sanos_no_aparecen_en_ninguna_alerta(): void
    {
        $serializado = json_encode(array_map(fn (Alert $a) => $a->jsonSerialize(), $this->scan()));

        $this->assertStringNotContainsString('SANO-1', $serializado);
        $this->assertStringNotContainsString('SANO-2', $serializado);
    }

    public function test_los_productos_inactivos_se_ignoran(): void
    {
        $serializado = json_encode(array_map(fn (Alert $a) => $a->jsonSerialize(), $this->scan()));

        $this->assertStringNotContainsString('INACTIVO', $serializado);
    }

    public function test_se_puede_silenciar_un_producto_por_configuracion(): void
    {
        $ignorado = DB::connection(self::CONNECTION)->table('items')
            ->where('internal_id', 'SIN-PRECIO')->value('id');

        config(['security-agent.catalog_anomalies.ignore_item_ids' => [$ignorado]]);

        $types = array_map(fn (Alert $a) => $a->type, $this->scan());

        $this->assertNotContains('precio_cero', $types);
    }

    public function test_sin_tabla_de_historial_el_modulo_sigue_funcionando(): void
    {
        Schema::connection(self::CONNECTION)->drop('item_price_history');

        $types = array_map(fn (Alert $a) => $a->type, $this->scan());

        $this->assertNotContains('caida_de_precio', $types);
        $this->assertContains('precio_cero', $types, 'El resto de detecciones debe seguir viva');
    }

    // ── Apoyo ─────────────────────────────────────────────────────────────────

    /** @return Alert[] */
    private function scan(): array
    {
        config(['security-agent.read_connection' => null]);

        $context = new ScanContext(
            $this->now,
            AgentConfig::load($this->workDir . DIRECTORY_SEPARATOR . 'no-existe.json'),
            new FileStateStore($this->workDir . DIRECTORY_SEPARATOR . 'state'),
        );

        return (new CatalogAnomalyDetector())->detect($context);
    }

    private function firstOfType(string $type): Alert
    {
        foreach ($this->scan() as $alert) {
            if ($alert->type === $type) return $alert;
        }

        $this->fail("No se genero ninguna alerta de tipo «{$type}»");
    }

    private function createSchema(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        $schema->create('items', function (Blueprint $table) {
            $table->increments('id');
            $table->string('internal_id')->nullable();
            $table->string('name')->nullable();          // vacio a proposito
            $table->string('description')->nullable();   // el nombre real
            $table->decimal('sale_unit_price', 16, 6)->nullable();
            $table->decimal('purchase_unit_price', 16, 6)->nullable();
            $table->decimal('min_margin_pct', 5, 2)->nullable();
            $table->decimal('mp_price', 12, 2)->nullable();
            $table->decimal('stock', 16, 4)->default(0);
            $table->boolean('active')->default(true);
        });

        $schema->create('item_warehouse', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('item_id');
            $table->unsignedInteger('warehouse_id')->default(1);
            $table->decimal('stock', 12, 4)->default(0);
        });

        $schema->create('item_price_history', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('item_id');
            $table->decimal('old_price', 12, 2);
            $table->decimal('new_price', 12, 2);
            $table->string('changed_by')->nullable();
            $table->string('source')->default('manual');
            $table->timestamp('created_at')->nullable();
        });
    }

    private function seedCatalog(): void
    {
        $items = [
            // Sanos: precio, costo y stock correctos. No deben salir nunca.
            $this->item('SANO-1', 'Polo sano', ['price' => 100, 'cost' => 50, 'stock' => 25]),
            $this->item('SANO-2', 'Gorra sana', ['price' => 60, 'cost' => 30, 'stock' => 40]),

            // Inactivo con todos los defectos: se ignora por estar despublicado.
            $this->item('INACTIVO', 'Descatalogado', ['price' => 0, 'cost' => 80, 'stock' => 0, 'active' => 0]),

            $this->item('SIN-PRECIO', 'Producto sin precio', ['price' => 0, 'cost' => 40, 'stock' => 10]),
            $this->item('BAJO-COSTO', 'Zapatilla bajo costo', ['price' => 10, 'cost' => 20, 'stock' => 15]),
            $this->item('MARGEN-FINO', 'Mochila margen fino', ['price' => 100, 'cost' => 95, 'stock' => 12]),
            $this->item('MARGEN-PROPIO', 'Reloj con minimo propio', ['price' => 100, 'cost' => 80, 'stock' => 9, 'min_margin' => 30]),
            $this->item('NEGATIVO', 'Casaca con stock negativo', ['price' => 200, 'cost' => 100, 'stock' => 5]),
            $this->item('AGOTADO', 'Camisa agotada publicada', ['price' => 80, 'cost' => 40, 'stock' => 0]),
            $this->item('CRITICO-1', 'Pantalon por agotarse', ['price' => 90, 'cost' => 45, 'stock' => 1]),
            $this->item('CRITICO-2', 'Correa por agotarse', ['price' => 30, 'cost' => 15, 'stock' => 2]),
            $this->item('CRITICO-3', 'Medias por agotarse', ['price' => 20, 'cost' => 10, 'stock' => 3]),
            $this->item('DISPAR', 'Audifonos con precio dispar', ['price' => 200, 'cost' => 100, 'stock' => 20, 'mp_price' => 100]),
            $this->item('BAJADA', 'Chaleco rebajado por error', ['price' => 60, 'cost' => 20, 'stock' => 30]),
            $this->item('SUBIDA', 'Billetera con decimal mal', ['price' => 200, 'cost' => 20, 'stock' => 30]),
        ];

        DB::connection(self::CONNECTION)->table('items')->insert($items);

        $id = fn (string $code) => DB::connection(self::CONNECTION)->table('items')->where('internal_id', $code)->value('id');

        DB::connection(self::CONNECTION)->table('item_warehouse')->insert([
            ['item_id' => $id('NEGATIVO'), 'warehouse_id' => 1, 'stock' => -5],
            ['item_id' => $id('SANO-1'),   'warehouse_id' => 1, 'stock' => 25],
        ]);

        DB::connection(self::CONNECTION)->table('item_price_history')->insert([
            [
                'item_id' => $id('BAJADA'), 'old_price' => 100, 'new_price' => 60,
                'changed_by' => 'importador@ebaemy.com', 'source' => 'import',
                'created_at' => $this->now->subHours(3)->toDateTimeString(),
            ],
            [
                'item_id' => $id('SUBIDA'), 'old_price' => 20, 'new_price' => 200,
                'changed_by' => 'ventas@ebaemy.com', 'source' => 'manual',
                'created_at' => $this->now->subHours(2)->toDateTimeString(),
            ],
            [
                // Cambio normal: no debe alertar.
                'item_id' => $id('SANO-2'), 'old_price' => 60, 'new_price' => 55,
                'changed_by' => 'ventas@ebaemy.com', 'source' => 'manual',
                'created_at' => $this->now->subHours(1)->toDateTimeString(),
            ],
        ]);
    }

    private function item(string $code, string $name, array $options): array
    {
        return [
            'internal_id'         => $code,
            'name'                => null,       // vacio, como en produccion
            'description'         => $name,
            'sale_unit_price'     => $options['price'],
            'purchase_unit_price' => $options['cost'],
            'min_margin_pct'      => $options['min_margin'] ?? null,
            'mp_price'            => $options['mp_price'] ?? null,
            'stock'               => $options['stock'],
            'active'              => $options['active'] ?? 1,
        ];
    }

    private function removeDir(string $dir): void
    {
        foreach ((array) glob($dir . DIRECTORY_SEPARATOR . '*') as $entry) {
            is_dir($entry) ? $this->removeDir($entry) : @unlink($entry);
        }

        @rmdir($dir);
    }
}
