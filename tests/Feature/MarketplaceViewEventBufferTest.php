<?php

namespace Tests\Feature;

use App\Services\Marketplace\ViewEventBuffer;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El buzon de vistas y clicks del marketplace.
 *
 * Cada ficha de producto hacia dos escrituras sincronas sobre LA MISMA fila
 * (`view_count + 1` y el upsert del dia). Con un producto popular MySQL las
 * serializa y la cola de locks frena al resto del sitio. Ahora la ficha solo
 * apunta el evento y el cron lo suma una vez por minuto.
 *
 * Lo que se prueba son las garantias de las que depende que la analitica siga
 * siendo correcta despues del cambio:
 *   1. nada se suma al apuntar, y se suma todo al agregar;
 *   2. el desglose por dia SUMA sobre lo que ya habia, no lo reemplaza;
 *   3. el limite de la pasada deja el resto para la siguiente, sin perderlo;
 *   4. un evento que ya no tiene producto, o que viene sin fecha, no deja el
 *      buzon atascado para siempre.
 *
 * Ver project_marketplace_carga_10k.
 */
class MarketplaceViewEventBufferTest extends TestCase
{
    private ViewEventBuffer $buffer;
    private int $listingId;

    protected function setUp(): void
    {
        parent::setUp();

        // Estas pruebas necesitan MySQL de verdad: el upsert del desglose usa
        // `ON DUPLICATE KEY UPDATE`, que no existe en SQLite. Si no se puede
        // hablar con la BD de pruebas se saltan en vez de fallar — mismo
        // criterio que MarketplaceHealthDetectorTest con pdo_sqlite.
        try {
            $this->prepararEsquema();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Requiere la BD de pruebas en MySQL: ' . $e->getMessage());
        }

        $this->buffer = app(ViewEventBuffer::class);
        $this->sys()->table('marketplace_view_events')->delete();
        $this->listingId = $this->crearListing();
    }

    protected function tearDown(): void
    {
        $this->sys()->table('marketplace_view_events')->delete();
        $this->sys()->table('marketplace_listing_stats_daily')->where('listing_id', $this->listingId)->delete();
        $this->sys()->table('marketplace_listings')->where('id', $this->listingId)->delete();

        parent::tearDown();
    }

    private function sys()
    {
        return DB::connection('system');
    }

    /**
     * Crea SOLO las tres tablas que estas pruebas tocan, si no estan.
     *
     * A proposito no se corre `migrate` entero contra la BD de pruebas: hay
     * tests que comprueban el comportamiento de un tenant SIN ciertas tablas
     * del marketplace (OrderShipmentFlowTest y el listado de pedidos), y
     * crearselas los haria fallar. Cada prueba trae lo suyo y nada mas.
     */
    private function prepararEsquema(): void
    {
        $schema = \Illuminate\Support\Facades\Schema::connection('system');

        if (!$schema->hasTable('marketplace_listings')) {
            $schema->create('marketplace_listings', function (\Illuminate\Database\Schema\Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('tenant_fqdn', 180)->nullable();
                $t->unsignedInteger('hostname_id')->nullable();
                $t->unsignedInteger('remote_item_id');
                $t->string('title', 250)->nullable();
                $t->string('slug', 255)->nullable();
                $t->string('image_url', 500)->nullable();
                $t->decimal('price', 12, 2)->default(0);
                $t->integer('stock')->default(0);
                $t->string('status', 20)->default('active');
                $t->boolean('is_active')->default(true);
                $t->unsignedInteger('view_count')->default(0);
                $t->unsignedInteger('click_count')->default(0);
                $t->timestamps();
            });
        }

        if (!$schema->hasTable('marketplace_listing_stats_daily')) {
            $schema->create('marketplace_listing_stats_daily', function (\Illuminate\Database\Schema\Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('listing_id');
                $t->unsignedInteger('hostname_id')->nullable();
                $t->date('stat_date');
                $t->unsignedInteger('views')->default(0);
                $t->unsignedInteger('clicks')->default(0);
                $t->timestamps();
                // La unica del upsert: sin ella el ON DUPLICATE KEY no agrupa.
                $t->unique(['listing_id', 'stat_date'], 'mls_listing_date_uq');
            });
        }

        if (!$schema->hasTable('marketplace_view_events')) {
            $schema->create('marketplace_view_events', function (\Illuminate\Database\Schema\Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('listing_id');
                $t->unsignedInteger('hostname_id')->nullable();
                $t->string('metric', 10);
                $t->timestamp('created_at')->nullable();
            });
        }
    }

    private function crearListing(int $views = 0, int $clicks = 0): int
    {
        return $this->sys()->table('marketplace_listings')->insertGetId([
            'tenant_fqdn'    => 'test.ebaemy.com',
            'remote_item_id' => 777001,
            'title'          => 'Producto de prueba del buzon',
            'slug'           => 'buzon-test-' . uniqid(),
            'image_url'      => 'https://test.ebaemy.com/storage/uploads/items/x.webp',
            'price'          => 10,
            'stock'          => 1,
            'status'         => 'active',
            'is_active'      => 1,
            'view_count'     => $views,
            'click_count'    => $clicks,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    private function listing()
    {
        return $this->sys()->table('marketplace_listings')->where('id', $this->listingId)->first();
    }

    private function dia()
    {
        return $this->sys()->table('marketplace_listing_stats_daily')
            ->where('listing_id', $this->listingId)->first();
    }

    public function test_apuntar_no_toca_los_contadores(): void
    {
        $this->buffer->record($this->listingId, 1, 'views');
        $this->buffer->record($this->listingId, 1, 'views');

        // Lo que importa del cambio: la peticion del visitante NO escribe en la
        // fila del producto, que es donde estaba la pelea por el lock.
        $this->assertSame(0, (int) $this->listing()->view_count);
        $this->assertNull($this->dia());
        $this->assertSame(2, $this->buffer->pending());
    }

    public function test_al_agregar_se_suman_vistas_y_clicks(): void
    {
        foreach (range(1, 5) as $i) $this->buffer->record($this->listingId, 1, 'views');
        foreach (range(1, 3) as $i) $this->buffer->record($this->listingId, 1, 'clicks');

        $r = $this->buffer->flush();

        $this->assertSame(8, $r['eventos']);
        $this->assertSame(1, $r['productos']);
        $this->assertSame(5, (int) $this->listing()->view_count);
        $this->assertSame(3, (int) $this->listing()->click_count);
        $this->assertSame(5, (int) $this->dia()->views);
        $this->assertSame(3, (int) $this->dia()->clicks);
        $this->assertSame(0, $this->buffer->pending(), 'el buzon tiene que quedar vacio');
    }

    public function test_el_desglose_del_dia_suma_en_vez_de_reemplazar(): void
    {
        foreach (range(1, 4) as $i) $this->buffer->record($this->listingId, 1, 'views');
        $this->buffer->flush();

        foreach (range(1, 3) as $i) $this->buffer->record($this->listingId, 1, 'views');
        $this->buffer->flush();

        // Si el upsert reemplazara, aqui habria 3 y se perderia media jornada
        // de analitica en cada pasada del cron.
        $this->assertSame(7, (int) $this->dia()->views);
        $this->assertSame(7, (int) $this->listing()->view_count);
    }

    public function test_el_limite_de_la_pasada_no_pierde_lo_que_deja_fuera(): void
    {
        foreach (range(1, 10) as $i) $this->buffer->record($this->listingId, 1, 'views');

        $r = $this->buffer->flush(4);

        $this->assertSame(4, $r['eventos']);
        $this->assertSame(4, (int) $this->listing()->view_count);
        $this->assertSame(6, $this->buffer->pending());

        $this->buffer->flush();

        $this->assertSame(10, (int) $this->listing()->view_count);
        $this->assertSame(0, $this->buffer->pending());
    }

    public function test_una_metrica_que_no_existe_no_se_apunta(): void
    {
        // El nombre de la metrica acaba dentro del SQL del upsert, asi que la
        // lista blanca es tambien la defensa contra inyeccion por ese hueco.
        $this->buffer->record($this->listingId, 1, 'DROP TABLE');
        $this->buffer->record($this->listingId, 1, 'leads');

        $this->assertSame(0, $this->buffer->pending());
    }

    public function test_un_evento_sin_producto_no_atasca_el_buzon(): void
    {
        $this->buffer->record(999999999, 1, 'views');
        $this->buffer->record($this->listingId, 1, 'views');

        $r = $this->buffer->flush();

        // El huerfano no suma a nadie pero SI se limpia: si se quedara, cada
        // pasada reintentaria lo mismo y el buzon no bajaria nunca.
        $this->assertSame(1, $r['eventos']);
        $this->assertSame(1, (int) $this->listing()->view_count);
        $this->assertSame(0, $this->buffer->pending());
    }

    public function test_un_evento_sin_fecha_no_tumba_la_pasada(): void
    {
        // stat_date es NOT NULL: sin la guarda, esta fila reventaria el upsert
        // y con el la transaccion, dejando el buzon atascado para siempre.
        $this->sys()->table('marketplace_view_events')->insert([
            'listing_id' => $this->listingId, 'hostname_id' => 1,
            'metric'     => 'views', 'created_at' => null,
        ]);
        $this->buffer->record($this->listingId, 1, 'views');

        $r = $this->buffer->flush();

        $this->assertSame(1, $r['eventos']);
        $this->assertSame(1, (int) $this->listing()->view_count);
        $this->assertSame(0, $this->buffer->pending());
    }

    public function test_agregar_sin_nada_pendiente_no_hace_nada(): void
    {
        $this->assertSame(
            ['eventos' => 0, 'productos' => 0, 'dias' => 0],
            $this->buffer->flush()
        );
    }
}
