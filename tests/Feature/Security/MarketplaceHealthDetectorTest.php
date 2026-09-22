<?php

namespace Tests\Feature\Security;

use App\Models\Tenant\MarketplaceChannel;
use App\Services\Security\Alert;
use App\Services\Security\Detectors\MarketplaceHealthDetector;
use App\Services\Security\ScanContext;
use App\Services\Security\Severity;
use App\Services\Security\State\FileStateStore;
use App\Services\Security\Support\AgentConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Modulo 4 — salud de cuentas en marketplaces.
 *
 * La conexion de prueba se llama `tenant` a proposito: el modelo
 * MarketplaceChannel usa UsesTenantConnection, asi que tiene que resolver a la
 * misma base en memoria que las consultas del detector.
 *
 * MercadoLibre va simulado con Http::fake(): el test no sale a internet.
 */
class MarketplaceHealthDetectorTest extends TestCase
{
    private const CONNECTION = 'tenant';

    private string $workDir;
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Requiere pdo_sqlite: php -d extension=php_pdo_sqlite.dll vendor/phpunit/phpunit/phpunit');
        }

        $this->workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sec-agent-mp-' . uniqid();
        mkdir($this->workDir, 0775, true);
        $this->now = CarbonImmutable::now();

        config([
            'database.connections.' . self::CONNECTION => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'database.default' => self::CONNECTION,
        ]);

        $this->createSchema();
        $this->seedChannels();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->workDir);

        parent::tearDown();
    }

    public function test_la_reputacion_roja_de_mercadolibre_es_critica(): void
    {
        $this->fakeMercadoLibre('1_red');

        $alert = $this->firstOfType('reputacion_en_riesgo');

        $this->assertSame(Severity::CRITICA, $alert->severity);
        $this->assertStringContainsString('roja', $alert->title);
        $this->assertStringContainsString('suspender la cuenta', $alert->recommendation);
    }

    public function test_la_reputacion_amarilla_es_media(): void
    {
        $this->fakeMercadoLibre('3_yellow');

        $this->assertSame(Severity::MEDIA, $this->firstOfType('reputacion_en_riesgo')->severity);
    }

    public function test_la_reputacion_naranja_es_alta(): void
    {
        $this->fakeMercadoLibre('2_orange');

        $this->assertSame(Severity::ALTA, $this->firstOfType('reputacion_en_riesgo')->severity);
    }

    public function test_la_reputacion_verde_no_alerta(): void
    {
        $this->fakeMercadoLibre('5_green', ['claims' => 0.0, 'cancellations' => 0.0, 'delayed' => 0.0], 0, 0);

        $types = array_map(fn (Alert $a) => $a->type, $this->scan());

        $this->assertNotContains('reputacion_en_riesgo', $types);
    }

    public function test_las_metricas_sobre_el_limite_alertan_con_su_valor(): void
    {
        $this->fakeMercadoLibre('5_green', ['claims' => 0.08, 'cancellations' => 0.0, 'delayed' => 0.0], 0, 0);

        $alerts = array_values(array_filter(
            $this->scan(),
            fn (Alert $a) => $a->type === 'metrica_sobre_limite' && ($a->evidence['metrica'] ?? '') === 'Reclamos'
        ));

        $this->assertNotEmpty($alerts);
        $this->assertSame(Severity::ALTA, $alerts[0]->severity);
        $this->assertSame(8.0, $alerts[0]->evidence['valor']);
        $this->assertSame(2, $alerts[0]->evidence['limite']);
        $this->assertSame('api', $alerts[0]->evidence['origen']);
    }

    public function test_falabella_se_evalua_con_metricas_locales(): void
    {
        $this->fakeMercadoLibre('5_green', ['claims' => 0.0, 'cancellations' => 0.0, 'delayed' => 0.0], 0, 0);

        $alertas = array_values(array_filter(
            $this->scan(),
            fn (Alert $a) => ($a->evidence['canal'] ?? '') === 'Saga Falabella'
                && $a->type === 'metrica_sobre_limite'
        ));

        $this->assertNotEmpty($alertas, 'Falabella no tiene API de salud: debe evaluarse con lo local');

        $metricas = array_column(array_column($alertas, 'evidence'), 'metrica');

        $this->assertContains('Cancelaciones', $metricas);   // 2 de 10 pedidos = 20 %
        $this->assertSame('local', $alertas[0]->evidence['origen']);
    }

    public function test_un_canal_sin_credenciales_avisa_en_vez_de_fallar(): void
    {
        // Por el modelo, no por update() masivo: `credentials` tiene cast cifrado.
        $channel = MarketplaceChannel::where('platform', 'mercadolibre')->first();
        $channel->credentials = [];
        $channel->save();

        $alert = $this->firstOfType('canal_sin_conexion');

        $this->assertSame(Severity::ALTA, $alert->severity);
        $this->assertStringContainsString('access_token', $alert->evidence['error']);
    }

    public function test_detecta_que_una_metrica_empeora_desde_la_revision_anterior(): void
    {
        $this->fakeMercadoLibre('5_green', ['claims' => 0.018, 'cancellations' => 0.0, 'delayed' => 0.0], 0, 0);

        $state   = new FileStateStore($this->workDir . DIRECTORY_SEPARATOR . 'state');
        $channel = MarketplaceChannel::where('platform', 'mercadolibre')->first();

        // Revision anterior: 1 % de reclamos. Ahora 1.8 % → +80 %, y sigue bajo
        // el limite del 2 %: es justo el caso que la regla de tendencia cubre.
        $state->put('marketplace_health', "system.channel.{$channel->id}", [
            'claims_pct' => 1.0,
            'at'         => $this->now->subDay()->toIso8601String(),
        ]);

        $alerts = (new MarketplaceHealthDetector())->detect($this->context($state));
        $alert  = collect($alerts)->firstWhere('type', 'metrica_empeorando');

        $this->assertNotNull($alert);
        $this->assertSame(Severity::ALTA, $alert->severity);
        $this->assertSame('80%', $alert->evidence['metricas']['claims_pct']['empeoro']);
    }

    public function test_sin_tablas_de_marketplace_el_modulo_no_revienta(): void
    {
        Schema::connection(self::CONNECTION)->drop('marketplace_channels');

        $this->assertSame([], $this->scan());
    }

    // ── Apoyo ─────────────────────────────────────────────────────────────────

    /** @return Alert[] */
    private function scan(): array
    {
        return (new MarketplaceHealthDetector())->detect(
            $this->context(new FileStateStore($this->workDir . DIRECTORY_SEPARATOR . 'state'))
        );
    }

    private function context(FileStateStore $state): ScanContext
    {
        config(['security-agent.read_connection' => null]);

        return new ScanContext(
            $this->now,
            AgentConfig::load($this->workDir . DIRECTORY_SEPARATOR . 'no-existe.json'),
            $state,
        );
    }

    private function firstOfType(string $type): Alert
    {
        foreach ($this->scan() as $alert) {
            if ($alert->type === $type) return $alert;
        }

        $this->fail("No se genero ninguna alerta de tipo «{$type}»");
    }

    private function fakeMercadoLibre(string $level, array $rates = ['claims' => 0.0, 'cancellations' => 0.0, 'delayed' => 0.0], int $paused = 0, int $questions = 0): void
    {
        Http::fake([
            'api.mercadolibre.com/users/SELLER-123' => Http::response([
                'seller_reputation' => [
                    'level_id' => $level,
                    'metrics'  => [
                        'claims'                => ['rate' => $rates['claims']],
                        'cancellations'         => ['rate' => $rates['cancellations']],
                        'delayed_handling_time' => ['rate' => $rates['delayed']],
                    ],
                ],
            ]),
            'api.mercadolibre.com/users/SELLER-123/items/search*' => Http::response(['paging' => ['total' => $paused]]),
            'api.mercadolibre.com/questions/search*'              => Http::response(['paging' => ['total' => $questions]]),
        ]);
    }

    private function createSchema(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        $schema->create('marketplace_channels', function (Blueprint $table) {
            $table->increments('id');
            $table->string('platform');
            $table->string('name');
            $table->string('status')->default('active');
            $table->text('credentials')->nullable();
            $table->text('settings')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();
        });

        $schema->create('marketplace_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('channel_id');
            $table->string('status')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('processed_at')->nullable();
        });

        $schema->create('marketplace_products', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('channel_id');
            $table->string('sync_status')->nullable();
        });
    }

    private function seedChannels(): void
    {
        // Se crean por el modelo para que `credentials` pase por su cast cifrado.
        $ml = MarketplaceChannel::create([
            'platform'    => 'mercadolibre',
            'name'        => 'MercadoLibre Perú',
            'status'      => 'active',
            'credentials' => ['access_token' => 'APP_USR-token-de-prueba', 'seller_id' => 'SELLER-123'],
        ]);

        $saga = MarketplaceChannel::create([
            'platform'    => 'falabella',
            'name'        => 'Saga Falabella',
            'status'      => 'active',
            'credentials' => ['api_key' => 'x', 'user_id' => 'y'],
        ]);

        // Falabella: 10 pedidos, 2 cancelados (20 %) y 3 despachados tarde.
        $orders = [];
        for ($i = 0; $i < 10; $i++) {
            $ordered = $this->now->subDays(10)->addHours($i);

            $orders[] = [
                'channel_id'   => $saga->id,
                'status'       => $i < 2 ? 'canceled' : 'delivered',
                'ordered_at'   => $ordered->toDateTimeString(),
                'processed_at' => $ordered->addHours($i < 5 ? 72 : 4)->toDateTimeString(),
            ];
        }

        // MercadoLibre sin pedidos locales: sus metricas salen de la API.
        DB::connection(self::CONNECTION)->table('marketplace_orders')->insert($orders);

        DB::connection(self::CONNECTION)->table('marketplace_products')->insert(
            array_map(fn () => ['channel_id' => $saga->id, 'sync_status' => 'paused'], range(1, 12))
        );
    }

    private function removeDir(string $dir): void
    {
        foreach ((array) glob($dir . DIRECTORY_SEPARATOR . '*') as $entry) {
            is_dir($entry) ? $this->removeDir($entry) : @unlink($entry);
        }

        @rmdir($dir);
    }
}
