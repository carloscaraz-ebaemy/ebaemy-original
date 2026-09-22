<?php

namespace Tests\Feature\Security;

use App\Services\Security\Alert;
use App\Services\Security\Detectors\UnauthorizedAccessDetector;
use App\Services\Security\ScanContext;
use App\Services\Security\Severity;
use App\Services\Security\State\FileStateStore;
use App\Services\Security\Support\AgentConfig;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;
use Tests\TestCase;

/**
 * Modulo 5 — ingreso no autorizado.
 *
 * Monta una base SQLite en memoria con `users` y `login_events`, siembra al
 * menos un caso de cada una de las siete detecciones y verifica severidad,
 * evidencia y recomendacion. La geolocalizacion va simulada: el test no sale
 * a internet.
 */
class UnauthorizedAccessDetectorTest extends TestCase
{
    private const CONNECTION = 'sec_agent_sqlite';

    private string $workDir;
    private CarbonImmutable $now;

    /** Cuando es false, el detector cae a la tabla `users` en vez de la lista blanca. */
    private bool $useWhitelist = true;

    protected function setUp(): void
    {
        parent::setUp();

        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped(
                'Este test monta la bitacora en SQLite en memoria. Habilita pdo_sqlite en php.ini '
                . 'o corre: php -d extension=php_pdo_sqlite.dll vendor/phpunit/phpunit/phpunit'
            );
        }

        $this->workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sec-agent-access-' . uniqid();
        mkdir($this->workDir, 0775, true);

        $this->now = CarbonImmutable::now();

        config([
            'database.connections.' . self::CONNECTION => [
                'driver'   => 'sqlite',
                'database' => ':memory:',
                'prefix'   => '',
            ],
            'database.default' => self::CONNECTION,
        ]);

        $this->createSchema();
        $this->seedUsers();
        $this->seedEvents();
        $this->fakeGeolocation();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->workDir);

        parent::tearDown();
    }

    public function test_detecta_un_caso_de_cada_tipo(): void
    {
        $types = array_map(fn (Alert $a) => $a->type, $this->scan());

        foreach ([
            'usuario_no_autorizado',
            'acceso_tras_fallos',
            'fuerza_bruta',
            'rociado_contrasenas',
            'pais_no_permitido',
            'ip_nueva',
            'viaje_imposible',
        ] as $expected) {
            $this->assertContains($expected, $types, "Falta la deteccion de «{$expected}»");
        }
    }

    public function test_el_usuario_fuera_de_la_lista_es_critico(): void
    {
        $alert = $this->firstOfType('usuario_no_autorizado');

        $this->assertSame(Severity::CRITICA, $alert->severity);
        $this->assertSame('intruso@externo.com', $alert->evidence['email']);
        $this->assertStringContainsString('desactiva', mb_strtolower($alert->recommendation));
    }

    public function test_el_ingreso_tras_varios_fallos_es_critico(): void
    {
        $alert = $this->firstOfType('acceso_tras_fallos');

        $this->assertSame(Severity::CRITICA, $alert->severity);
        $this->assertSame('ventas@ebaemy.com', $alert->evidence['email']);
        $this->assertGreaterThanOrEqual(3, $alert->evidence['fallos_previos']);
    }

    public function test_la_fuerza_bruta_es_alta_y_cuenta_los_intentos(): void
    {
        $alert = $this->firstOfType('fuerza_bruta');

        $this->assertSame(Severity::ALTA, $alert->severity);
        $this->assertSame('soporte@ebaemy.com', $alert->evidence['email']);
        $this->assertSame(6, $alert->evidence['intentos']);
    }

    public function test_el_rociado_agrupa_los_usuarios_por_ip(): void
    {
        $alert = $this->firstOfType('rociado_contrasenas');

        $this->assertSame(Severity::ALTA, $alert->severity);
        $this->assertSame('203.0.113.50', $alert->evidence['ip']);
        $this->assertCount(5, $alert->evidence['usuarios']);
    }

    public function test_el_pais_no_permitido_identifica_el_origen(): void
    {
        $alert = $this->firstOfType('pais_no_permitido');

        $this->assertSame(Severity::ALTA, $alert->severity);
        $this->assertSame('US', $alert->evidence['pais']);
        $this->assertSame('viajero@ebaemy.com', $alert->evidence['email']);
    }

    public function test_el_viaje_imposible_es_critico(): void
    {
        $alert = $this->firstOfType('viaje_imposible');

        $this->assertSame(Severity::CRITICA, $alert->severity);
        $this->assertSame('PE', $alert->evidence['pais_anterior']);
        $this->assertSame('US', $alert->evidence['pais_actual']);
        $this->assertLessThan(4, $alert->evidence['horas']);
    }

    public function test_la_ip_nueva_es_media_y_solo_para_usuarios_ya_conocidos(): void
    {
        $alert = $this->firstOfType('ip_nueva');

        $this->assertSame(Severity::MEDIA, $alert->severity);
        $this->assertSame('190.12.0.99', $alert->evidence['ip']);
    }

    public function test_la_primera_corrida_aprende_las_ips_sin_alertar(): void
    {
        // Estado virgen: el agente no ha visto nunca a estos usuarios.
        $state   = new FileStateStore($this->workDir . DIRECTORY_SEPARATOR . 'virgen');
        $context = $this->context($state);

        $types = array_map(fn (Alert $a) => $a->type, (new UnauthorizedAccessDetector())->detect($context));

        $this->assertNotContains('ip_nueva', $types, 'La primera corrida no debe inundar de alertas de IP nueva');
        // Las demas detecciones si funcionan desde el primer escaneo.
        $this->assertContains('fuerza_bruta', $types);
        $this->assertTrue((bool) $state->get('known_ips', 'system.seeded'));
    }

    public function test_sin_lista_blanca_se_usa_la_tabla_de_usuarios(): void
    {
        $this->useWhitelist = false;

        $alert = $this->firstOfType('usuario_no_autorizado');

        // intruso@externo.com no esta en `users`, asi que sigue siendo intruso.
        $this->assertSame('intruso@externo.com', $alert->evidence['email']);
    }

    public function test_un_usuario_bloqueado_en_la_tabla_cuenta_como_no_autorizado(): void
    {
        $this->useWhitelist = false;

        DB::connection(self::CONNECTION)->table('users')
            ->where('email', 'ventas@ebaemy.com')->update(['locked' => 1]);

        $emails = array_map(
            fn (Alert $a) => $a->evidence['email'],
            array_values(array_filter($this->scan(), fn (Alert $a) => $a->type === 'usuario_no_autorizado'))
        );

        $this->assertContains('ventas@ebaemy.com', $emails);
    }

    // ── Apoyo ─────────────────────────────────────────────────────────────────

    /** @return Alert[] */
    private function scan(): array
    {
        return (new UnauthorizedAccessDetector())->detect($this->context($this->primedState()));
    }

    private function firstOfType(string $type): Alert
    {
        foreach ($this->scan() as $alert) {
            if ($alert->type === $type) return $alert;
        }

        $this->fail("No se genero ninguna alerta de tipo «{$type}»");
    }

    private function context(FileStateStore $state): ScanContext
    {
        config([
            'security-agent.unauthorized_access.authorized_emails' => $this->useWhitelist ? [
                'ventas@ebaemy.com', 'soporte@ebaemy.com',
                'viajero@ebaemy.com', 'nuevo@ebaemy.com',
            ] : [],
            'security-agent.unauthorized_access.allowed_countries' => ['PE'],
            'security-agent.read_connection' => null,
        ]);

        return new ScanContext(
            $this->now,
            AgentConfig::load($this->workDir . DIRECTORY_SEPARATOR . 'no-existe.json'),
            $state,
        );
    }

    /**
     * Estado de un agente que YA lleva tiempo corriendo: conoce las IPs
     * habituales de cada usuario. Solo asi tiene sentido la alerta de IP nueva.
     */
    private function primedState(): FileStateStore
    {
        $state = new FileStateStore($this->workDir . DIRECTORY_SEPARATOR . 'state');

        $state->put('known_ips', 'system.seeded', true);
        $state->put('known_ips', 'system.ips.intruso@externo.com', ['190.12.0.1']);
        $state->put('known_ips', 'system.ips.ventas@ebaemy.com', ['190.12.0.2']);
        $state->put('known_ips', 'system.ips.viajero@ebaemy.com', ['190.12.0.3', '8.8.8.8']);
        $state->put('known_ips', 'system.ips.nuevo@ebaemy.com', ['190.12.0.4']);

        return $state;
    }

    private function createSchema(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        $schema->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email');
            $table->boolean('active')->default(true);
            $table->boolean('locked')->default(false);
        });

        $schema->create('login_events', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('guard')->nullable();
            $table->string('email')->nullable();
            $table->string('result');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    private function seedUsers(): void
    {
        DB::connection(self::CONNECTION)->table('users')->insert([
            ['email' => 'ventas@ebaemy.com',   'active' => 1, 'locked' => 0],
            ['email' => 'soporte@ebaemy.com',  'active' => 1, 'locked' => 0],
            ['email' => 'viajero@ebaemy.com',  'active' => 1, 'locked' => 0],
            ['email' => 'nuevo@ebaemy.com',    'active' => 1, 'locked' => 0],
        ]);
    }

    private function seedEvents(): void
    {
        $rows = [];

        // 1. Intruso que no esta en la lista de autorizados.
        $rows[] = $this->event('intruso@externo.com', 'success', '190.12.0.1', 30);

        // 2. Cuatro fallos y despues un ingreso exitoso (cuenta comprometida).
        foreach ([26, 25, 24, 23] as $minutes) {
            $rows[] = $this->event('ventas@ebaemy.com', 'failed', '190.12.0.2', $minutes);
        }
        $rows[] = $this->event('ventas@ebaemy.com', 'success', '190.12.0.2', 22);

        // 3. Fuerza bruta: seis fallos contra la misma cuenta, sin exito.
        for ($i = 0; $i < 6; $i++) {
            $rows[] = $this->event('soporte@ebaemy.com', 'failed', '203.0.113.60', 10 - $i * 0.5);
        }

        // 4. Rociado de contrasenas: una IP probando cinco cuentas distintas.
        foreach (['a@ebaemy.com', 'b@ebaemy.com', 'c@ebaemy.com', 'd@ebaemy.com', 'e@ebaemy.com'] as $i => $email) {
            $rows[] = $this->event($email, 'failed', '203.0.113.50', 5 - $i * 0.2);
        }

        // 5 y 7. Mismo usuario desde Peru y desde Estados Unidos en 40 minutos.
        $rows[] = $this->event('viajero@ebaemy.com', 'success', '190.12.0.3', 50);
        $rows[] = $this->event('viajero@ebaemy.com', 'success', '8.8.8.8', 10);

        // 6. Usuario conocido entrando desde una IP peruana nunca vista.
        $rows[] = $this->event('nuevo@ebaemy.com', 'success', '190.12.0.99', 8);

        DB::connection(self::CONNECTION)->table('login_events')->insert($rows);
    }

    private function event(string $email, string $result, string $ip, float $minutesAgo): array
    {
        return [
            'email'      => $email,
            'result'     => $result,
            'ip_address' => $ip,
            'guard'      => 'web',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'url'        => 'https://tienda.ebaemy.com/login',
            'created_at' => $this->now->subSeconds((int) round($minutesAgo * 60))->toDateTimeString(),
        ];
    }

    private function fakeGeolocation(): void
    {
        Location::fake([
            '190.12.*' => $this->position('PE', 'Lima', -12.0464, -77.0428),
            '203.0.*'  => $this->position('PE', 'Lima', -12.0464, -77.0428),
            '8.8.8.8'  => $this->position('US', 'Mountain View', 37.386, -122.084),
        ]);
    }

    private function position(string $country, string $city, float $lat, float $lon): Position
    {
        $position = new Position();
        $position->countryCode = $country;
        $position->cityName    = $city;
        $position->latitude    = (string) $lat;
        $position->longitude   = (string) $lon;
        $position->driver      = 'fake';

        return $position;
    }

    private function removeDir(string $dir): void
    {
        foreach ((array) glob($dir . DIRECTORY_SEPARATOR . '*') as $entry) {
            is_dir($entry) ? $this->removeDir($entry) : @unlink($entry);
        }

        @rmdir($dir);
    }
}
