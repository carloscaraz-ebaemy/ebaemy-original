<?php

namespace Tests\Feature\Security;

use App\Services\Security\Alert;
use App\Services\Security\Detectors\OrderFraudDetector;
use App\Services\Security\ScanContext;
use App\Services\Security\Severity;
use App\Services\Security\State\FileStateStore;
use App\Services\Security\Support\AgentConfig;
use App\Services\Security\Support\CardFingerprint;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;
use Tests\TestCase;

/**
 * Modulo 1 — fraude en pedidos y pagos.
 *
 * Siembra pedidos simulados con al menos un caso de cada una de las ocho
 * senales de riesgo, mas un pedido perfectamente normal que NO debe alertar.
 */
class OrderFraudDetectorTest extends TestCase
{
    private const CONNECTION = 'sec_agent_sqlite';

    private string $workDir;
    private CarbonImmutable $now;
    private string $sharedCard;

    protected function setUp(): void
    {
        parent::setUp();

        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Requiere pdo_sqlite: php -d extension=php_pdo_sqlite.dll vendor/phpunit/phpunit/phpunit');
        }

        $this->workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sec-agent-fraud-' . uniqid();
        mkdir($this->workDir, 0775, true);

        // Mediodia, para que ningun pedido caiga en madrugada sin querer.
        $this->now        = CarbonImmutable::now()->setTime(14, 0);
        $this->sharedCard = CardFingerprint::make('411111', '1111');

        config([
            'database.connections.' . self::CONNECTION => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'database.default' => self::CONNECTION,
        ]);

        $this->createSchema();
        $this->seedBaseline();
        $this->seedSuspiciousOrders();

        Location::fake([
            '190.12.*' => $this->position('PE'),
            '8.8.8.8'  => $this->position('US'),
        ]);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->workDir);

        parent::tearDown();
    }

    public function test_cubre_las_ocho_senales_de_riesgo(): void
    {
        $reasons = [];
        foreach ($this->scan() as $alert) {
            $reasons = array_merge($reasons, $alert->evidence['razones']);
        }

        $texto = mb_strtolower(implode(' | ', $reasons));

        foreach ([
            'supera 4x la mediana',
            'supera el maximo configurado',
            'la ip esta en us pero el envio va a pe',
            'dominio desechable',
            'pedidos en 24 h desde la misma ip',
            'clientes distintos',
            'pedidos del mismo cliente',
            'horario de madrugada',
        ] as $expected) {
            $this->assertStringContainsString($expected, $texto, "Falta la senal «{$expected}»");
        }
    }

    public function test_el_pedido_normal_no_genera_alerta(): void
    {
        foreach ($this->scan() as $alert) {
            $this->assertNotSame('P-NORMAL', $alert->evidence['pedido']);
        }
    }

    public function test_el_pedido_con_todas_las_senales_es_critico(): void
    {
        $alert = $this->forOrder('P-CRITICO');

        $this->assertSame(Severity::CRITICA, $alert->severity);
        $this->assertSame(100, $alert->evidence['puntaje'], 'El puntaje se tope en 100');
        $this->assertStringContainsString('NO despaches', $alert->recommendation);
    }

    public function test_el_monto_desmedido_solo_llega_a_media(): void
    {
        $alert = $this->forOrder('P-MONTO');

        $this->assertSame(Severity::MEDIA, $alert->severity);
        $this->assertSame(45, $alert->evidence['puntaje']);
    }

    public function test_la_evidencia_no_expone_el_numero_de_tarjeta(): void
    {
        $alert = $this->forOrder('P-CRITICO');

        $this->assertSame('****1111', $alert->evidence['tarjeta']);

        $serializado = json_encode($alert->jsonSerialize());
        $this->assertStringNotContainsString($this->sharedCard, $serializado, 'La huella HMAC no debe salir en la alerta');
        $this->assertDoesNotMatchRegularExpression('/\b\d{13,19}\b/', $serializado, 'No debe aparecer nada parecido a un PAN');
    }

    public function test_la_huella_de_tarjeta_es_estable_y_no_reversible(): void
    {
        $a = CardFingerprint::make('411111', '1111');
        $b = CardFingerprint::make('411111', '1111');
        $c = CardFingerprint::make('522222', '1111');

        $this->assertSame($a, $b, 'La misma tarjeta siempre da la misma huella');
        $this->assertNotSame($a, $c, 'Tarjetas distintas dan huellas distintas');
        $this->assertSame(64, strlen($a));
        $this->assertStringNotContainsString('411111', $a);
        $this->assertNull(CardFingerprint::make('411111', null));
    }

    public function test_la_huella_sale_de_la_respuesta_de_culqi_sin_el_numero(): void
    {
        $charge = json_decode(json_encode([
            'id'     => 'chr_test_123',
            'source' => [
                'card_number' => '411111******1111',
                'iin'         => ['bin' => '411111', 'card_brand' => 'Visa'],
            ],
        ]));

        $context = CardFingerprint::fromCulqiCharge($charge);

        $this->assertSame('1111', $context['last4']);
        $this->assertSame(CardFingerprint::make('411111', '1111'), $context['fingerprint']);
    }

    public function test_una_respuesta_de_culqi_sin_tarjeta_no_revienta(): void
    {
        $context = CardFingerprint::fromCulqiCharge(json_decode(json_encode(['id' => 'chr_x'])));

        $this->assertNull($context['last4']);
        $this->assertNull($context['fingerprint']);
    }

    // ── Apoyo ─────────────────────────────────────────────────────────────────

    /** @return Alert[] */
    private function scan(): array
    {
        config([
            'security-agent.order_fraud.max_amount'        => 5000,
            'security-agent.order_fraud.median_multiplier' => 4,
            'security-agent.read_connection'               => null,
        ]);

        $context = new ScanContext(
            $this->now,
            AgentConfig::load($this->workDir . DIRECTORY_SEPARATOR . 'no-existe.json'),
            new FileStateStore($this->workDir . DIRECTORY_SEPARATOR . 'state'),
        );

        return (new OrderFraudDetector())->detect($context);
    }

    private function forOrder(string $reference): Alert
    {
        foreach ($this->scan() as $alert) {
            if ($alert->evidence['pedido'] === $reference) return $alert;
        }

        $this->fail("No se genero alerta para el pedido {$reference}");
    }

    private function createSchema(): void
    {
        Schema::connection(self::CONNECTION)->create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('external_id')->nullable();
            $table->string('number_document')->nullable();
            $table->text('customer')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->string('card_fingerprint', 64)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    /** Historial normal: 40 pedidos de S/ 100, para que la mediana sea 100. */
    private function seedBaseline(): void
    {
        $rows = [];

        for ($i = 0; $i < 40; $i++) {
            $rows[] = $this->order("H-{$i}", 100, [
                'ip'    => '190.12.0.' . (10 + $i % 20),
                'email' => "cliente{$i}@gmail.com",
                'when'  => $this->now->subDays(2 + $i),
            ]);
        }

        DB::connection(self::CONNECTION)->table('orders')->insert($rows);
    }

    private function seedSuspiciousOrders(): void
    {
        $madrugada = $this->now->setTime(3, 15)->lte($this->now)
            ? $this->now->setTime(3, 15)
            : $this->now->subDay()->setTime(3, 15);

        $rows = [
            // Control: todo en orden. No debe alertar.
            $this->order('P-NORMAL', 120, ['ip' => '190.12.0.1', 'email' => 'buena@gmail.com']),

            // Monto: sobre el maximo (25) y sobre 4x la mediana (20) = 45 → MEDIA.
            $this->order('P-MONTO', 6000, ['ip' => '190.12.0.5', 'email' => 'normal@gmail.com']),

            // Todas las senales de monto + IP extranjera + correo desechable +
            // tarjeta compartida. Se tope en 100 → CRITICA.
            $this->order('P-CRITICO', 6000, [
                'ip'    => '8.8.8.8',
                'email' => 'usarytirar@mailinator.com',
                'card'  => ['1111', $this->sharedCard],
            ]),

            // Segundo cliente con LA MISMA tarjeta: es lo que hace compartida la del anterior.
            $this->order('P-OTRO-DUENO', 150, [
                'ip'    => '190.12.0.6',
                'email' => 'otrapersona@gmail.com',
                'card'  => ['1111', $this->sharedCard],
            ]),

            // Tres pedidos del mismo cliente desde la misma IP, uno de madrugada.
            $this->order('P-RAFAGA-1', 200, ['ip' => '190.12.0.77', 'email' => 'rafaga@yopmail.com', 'when' => $madrugada]),
            $this->order('P-RAFAGA-2', 210, ['ip' => '190.12.0.77', 'email' => 'rafaga@yopmail.com', 'when' => $madrugada->addMinutes(20)]),
            $this->order('P-RAFAGA-3', 220, ['ip' => '190.12.0.77', 'email' => 'rafaga@yopmail.com', 'when' => $madrugada->addMinutes(40)]),
        ];

        DB::connection(self::CONNECTION)->table('orders')->insert($rows);
    }

    private function order(string $reference, float $total, array $options = []): array
    {
        $when = $options['when'] ?? $this->now->subHours(2);

        return [
            'external_id'      => strtolower($reference),
            'number_document'  => $reference,
            'customer'         => json_encode([
                'correo_electronico' => $options['email'] ?? 'cliente@gmail.com',
                'codigo_pais'        => $options['country'] ?? 'PE',
                'telefono'           => '999888777',
                'direccion'          => 'Av. Siempre Viva 742',
            ]),
            'total'            => $total,
            'ip_address'       => $options['ip'] ?? null,
            'user_agent'       => 'Mozilla/5.0',
            'card_last4'       => $options['card'][0] ?? null,
            'card_fingerprint' => $options['card'][1] ?? null,
            'created_at'       => $when->toDateTimeString(),
            'deleted_at'       => null,
        ];
    }

    private function position(string $country): Position
    {
        $position = new Position();
        $position->countryCode = $country;
        $position->cityName    = $country === 'PE' ? 'Lima' : 'Mountain View';
        $position->latitude    = '0';
        $position->longitude   = '0';
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
