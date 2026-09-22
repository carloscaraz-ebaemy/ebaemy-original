<?php

namespace Tests\Feature\Security;

use App\Services\Security\Alert;
use App\Services\Security\Detectors\WorkScheduleDetector;
use App\Services\Security\ScanContext;
use App\Services\Security\Severity;
use App\Services\Security\State\FileStateStore;
use App\Services\Security\Support\AgentConfig;
use App\Services\Security\Support\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Modulo 6 — horarios de trabajo.
 *
 * El "ahora" de la corrida es el lunes 2026-09-21 a las 10:00, dentro de la
 * jornada. Los eventos sembrados cubren la noche del domingo, la madrugada del
 * lunes y la jornada normal.
 */
class WorkScheduleDetectorTest extends TestCase
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

        $this->workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sec-agent-schedule-' . uniqid();
        mkdir($this->workDir, 0775, true);

        $this->now = CarbonImmutable::parse('2026-09-21 10:00:00'); // lunes
        $this->assertSame('Mon', $this->now->format('D'));

        config([
            'database.connections.' . self::CONNECTION => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'database.default' => self::CONNECTION,
        ]);

        $this->createSchema();
        $this->seedEvents();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->workDir);

        parent::tearDown();
    }

    // ── La jornada en si ──────────────────────────────────────────────────────

    public function test_la_jornada_reconoce_dias_horas_y_tolerancia(): void
    {
        $schedule = $this->schedule();

        $this->assertTrue($schedule->covers(CarbonImmutable::parse('2026-09-21 09:00')), 'Lunes a media manana');
        $this->assertTrue($schedule->covers(CarbonImmutable::parse('2026-09-21 07:50')), 'Dentro de los 15 min de tolerancia');
        $this->assertFalse($schedule->covers(CarbonImmutable::parse('2026-09-21 07:30')), 'Antes de la tolerancia');
        $this->assertTrue($schedule->covers(CarbonImmutable::parse('2026-09-21 19:10')), 'Tolerancia al cierre');
        $this->assertFalse($schedule->covers(CarbonImmutable::parse('2026-09-21 21:00')), 'Despues del cierre');
        $this->assertFalse($schedule->covers(CarbonImmutable::parse('2026-09-20 11:00')), 'Domingo no laborable');
        $this->assertTrue($schedule->covers(CarbonImmutable::parse('2026-09-19 10:00')), 'Sabado por la manana');
        $this->assertFalse($schedule->covers(CarbonImmutable::parse('2026-09-19 18:00')), 'Sabado por la tarde');
    }

    public function test_los_feriados_no_son_laborables(): void
    {
        $schedule = $this->schedule(['holidays' => ['2026-09-21']]);

        $this->assertSame('feriado', $schedule->reasonOutside(CarbonImmutable::parse('2026-09-21 10:00')));
    }

    public function test_un_turno_especial_pisa_el_horario_general(): void
    {
        $schedule = $this->schedule([
            'user_exceptions' => ['nocturno@ebaemy.com' => ['mon' => ['22:00', '06:00']]],
        ]);

        $lunesNoche = CarbonImmutable::parse('2026-09-21 23:30');

        $this->assertFalse($schedule->covers($lunesNoche), 'Para el resto del equipo son horas intempestivas');
        $this->assertTrue($schedule->covers($lunesNoche, 'nocturno@ebaemy.com'), 'Para el del turno noche es su jornada');

        // El turno cruza la medianoche: las 03:00 del martes siguen siendo suyas.
        $this->assertTrue($schedule->covers(CarbonImmutable::parse('2026-09-22 03:00'), 'nocturno@ebaemy.com'));
    }

    // ── El detector ───────────────────────────────────────────────────────────

    public function test_detecta_actividad_fuera_de_horario_agrupada_por_usuario_y_dia(): void
    {
        $alert = $this->firstOfType('actividad_fuera_de_horario');

        $this->assertSame(Severity::MEDIA, $alert->severity);
        $this->assertSame('vendedor@ebaemy.com', $alert->evidence['usuario']);
        $this->assertSame('2026-09-20', $alert->evidence['dia']);
        $this->assertSame('dia no laborable', $alert->evidence['motivo']);
        $this->assertSame(0, $alert->evidence['sensibles']);
    }

    public function test_la_actividad_sensible_fuera_de_horario_sube_a_alta(): void
    {
        $alert = $this->firstOfType('actividad_sensible_fuera_de_horario');

        $this->assertSame(Severity::ALTA, $alert->severity);
        $this->assertSame('jefe@ebaemy.com', $alert->evidence['usuario']);
        $this->assertGreaterThan(0, $alert->evidence['sensibles']);
        $this->assertStringContainsString('ingreso no autorizado', $alert->recommendation);
    }

    public function test_detecta_la_rafaga_de_acciones_sensibles(): void
    {
        $alert = $this->firstOfType('rafaga_acciones_sensibles');

        $this->assertSame(Severity::ALTA, $alert->severity);
        $this->assertSame('jefe@ebaemy.com', $alert->evidence['usuario']);
        $this->assertGreaterThanOrEqual(20, $alert->evidence['acciones']);
    }

    public function test_el_reporte_de_jornada_va_en_baja_y_no_notifica(): void
    {
        $alert = $this->firstOfType('reporte_de_jornada');

        $this->assertSame(Severity::BAJA, $alert->severity);
        $this->assertFalse(
            Severity::atLeast($alert->severity, Severity::ALTA),
            'El reporte diario nunca debe disparar una notificacion'
        );

        $fila = collect($alert->evidence['usuarios'])->firstWhere('usuario', 'oficina@ebaemy.com');

        $this->assertNotNull($fila);
        $this->assertSame('09:05', $fila['primer_evento']);
        $this->assertSame('18:40', $fila['ultimo_evento']);
        $this->assertSame(0, $fila['fuera_de_horario']);
    }

    public function test_el_usuario_con_turno_especial_no_genera_alerta(): void
    {
        config(['security-agent.work_schedule.user_exceptions' => [
            'nocturno@ebaemy.com' => ['sun' => ['20:00', '23:59'], 'mon' => ['00:00', '06:00']],
        ]]);

        foreach ($this->scan() as $alert) {
            $this->assertNotSame('nocturno@ebaemy.com', $alert->evidence['usuario'] ?? null);
        }
    }

    public function test_si_falta_una_fuente_las_otras_siguen_funcionando(): void
    {
        Schema::connection(self::CONNECTION)->drop('item_price_history');

        $types = array_map(fn (Alert $a) => $a->type, $this->scan());

        $this->assertContains('actividad_fuera_de_horario', $types);
        $this->assertContains('reporte_de_jornada', $types);
    }

    // ── Apoyo ─────────────────────────────────────────────────────────────────

    private function schedule(array $overrides = []): WorkSchedule
    {
        return new WorkSchedule(
            $overrides['schedule'] ?? [
                'mon' => ['08:00', '19:00'], 'tue' => ['08:00', '19:00'],
                'wed' => ['08:00', '19:00'], 'thu' => ['08:00', '19:00'],
                'fri' => ['08:00', '19:00'], 'sat' => ['09:00', '13:00'],
                'sun' => null,
            ],
            $overrides['holidays'] ?? [],
            $overrides['tolerance'] ?? 15,
            $overrides['user_exceptions'] ?? [],
        );
    }

    /** @return Alert[] */
    private function scan(): array
    {
        config(['security-agent.read_connection' => null]);

        $context = new ScanContext(
            $this->now,
            AgentConfig::load($this->workDir . DIRECTORY_SEPARATOR . 'no-existe.json'),
            new FileStateStore($this->workDir . DIRECTORY_SEPARATOR . 'state'),
        );

        return (new WorkScheduleDetector())->detect($context);
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

        $schema->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email');
        });

        $schema->create('login_events', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('email')->nullable();
            $table->string('result');
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        $schema->create('item_price_history', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('item_id')->default(1);
            $table->decimal('old_price', 12, 2)->default(0);
            $table->decimal('new_price', 12, 2)->default(0);
            $table->string('change_type')->default('price');
            $table->string('changed_by')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        $schema->create('audit_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('action');
            $table->string('module');
            $table->timestamp('created_at')->nullable();
        });
    }

    private function seedEvents(): void
    {
        $users = [
            ['email' => 'oficina@ebaemy.com'],
            ['email' => 'vendedor@ebaemy.com'],
            ['email' => 'jefe@ebaemy.com'],
            ['email' => 'nocturno@ebaemy.com'],
        ];
        DB::connection(self::CONNECTION)->table('users')->insert($users);

        $logins = [
            // Jornada normal del lunes: entra 09:05, sale 18:40.
            $this->login('oficina@ebaemy.com', '2026-09-21 09:05:00'),
            $this->login('oficina@ebaemy.com', '2026-09-21 18:40:00', 'logout'),

            // Domingo por la noche: fuera de horario, sin acciones sensibles.
            $this->login('vendedor@ebaemy.com', '2026-09-20 20:15:00'),
            $this->login('vendedor@ebaemy.com', '2026-09-20 21:00:00', 'logout'),

            // Madrugada del lunes, con cambios de precio detras.
            $this->login('jefe@ebaemy.com', '2026-09-21 02:10:00'),

            // Turno noche: domingo 22:00 y madrugada del lunes.
            $this->login('nocturno@ebaemy.com', '2026-09-20 22:05:00'),
            $this->login('nocturno@ebaemy.com', '2026-09-21 05:30:00', 'logout'),
        ];
        DB::connection(self::CONNECTION)->table('login_events')->insert($logins);

        // 25 cambios de precio de madrugada en media hora: rafaga sensible.
        $priceRows = [];
        for ($i = 0; $i < 25; $i++) {
            $priceRows[] = [
                'item_id'     => 100 + $i,
                'old_price'   => 100,
                'new_price'   => 80,
                'change_type' => 'price',
                'changed_by'  => 'jefe@ebaemy.com',
                'created_at'  => CarbonImmutable::parse('2026-09-21 02:15:00')->addMinutes($i)->toDateTimeString(),
            ];
        }
        DB::connection(self::CONNECTION)->table('item_price_history')->insert($priceRows);

        // Accion sensible registrada en audit_logs dentro de la jornada.
        DB::connection(self::CONNECTION)->table('audit_logs')->insert([
            [
                'user_id'    => 1,
                'action'     => 'create',
                'module'     => 'user',
                'created_at' => '2026-09-21 11:00:00',
            ],
        ]);
    }

    private function login(string $email, string $at, string $result = 'success'): array
    {
        return [
            'email'      => $email,
            'result'     => $result,
            'ip_address' => '190.12.0.1',
            'created_at' => $at,
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
