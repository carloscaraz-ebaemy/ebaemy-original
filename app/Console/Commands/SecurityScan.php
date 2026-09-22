<?php

namespace App\Console\Commands;

use App\Services\Security\Alert;
use App\Services\Security\Notify\NotificationDispatcher;
use App\Services\Security\Output\HtmlReport;
use App\Services\Security\Output\JsonlWriter;
use App\Services\Security\SecurityAgent;
use App\Services\Security\Severity;
use App\Services\Security\State\FileStateStore;
use App\Services\Security\Support\AgentConfig;
use App\Services\Security\Support\IntegrityMonitor;
use Illuminate\Console\Command;

/**
 * Punto de entrada del agente de seguridad.
 *
 *   php artisan security:scan                      corrida normal
 *   php artisan security:scan --module=web_attacks solo un modulo
 *   php artisan security:scan --tenant=<uuid>      solo un tenant
 *   php artisan security:scan --dry-run            no guarda estado ni notifica
 *   php artisan security:scan --init-config        crea el config.json comentado
 *   php artisan security:scan --accept-integrity   acepta los hashes actuales
 */
class SecurityScan extends Command
{
    protected $signature = 'security:scan
        {--tenant= : UUID de un solo tenant}
        {--module= : Correr un solo modulo (unauthorized_access, web_attacks, ...)}
        {--dry-run : No persiste estado ni envia notificaciones}
        {--no-notify : Corre y reporta, pero no notifica}
        {--init-config : Crea storage/app/security-agent/config.json comentado}
        {--accept-integrity : Marca los hashes actuales como linea base valida}';

    protected $description = 'Agente de seguridad: detecta riesgos, los clasifica y notifica (solo lectura)';

    public function handle(): int
    {
        if ($this->option('init-config')) {
            return $this->initConfig();
        }

        if ($this->option('accept-integrity')) {
            return $this->acceptIntegrity();
        }

        $config = AgentConfig::load();

        if (!$config->get('enabled', true)) {
            $this->warn('El agente esta desactivado (security-agent.enabled = false).');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->info('Agente de Seguridad — iniciando escaneo' . ($dryRun ? ' (dry-run)' : ''));

        $summary = (new SecurityAgent())->run([
            'tenant'  => $this->option('tenant') ?: null,
            'module'  => $this->option('module') ?: null,
            'dry_run' => $dryRun,
        ]);

        /** @var Alert[] $alerts */
        $alerts = $summary['alerts'];

        if ($config->get('output.console', true)) {
            $this->renderConsole($alerts, $summary);
        }

        if (!$dryRun && $config->get('output.jsonl', true)) {
            $jsonl = JsonlWriter::make();
            $jsonl->append($alerts);
            $jsonl->prune((int) $config->get('retention.alerts_jsonl_days', 90));
            $this->line('  Historial: ' . $jsonl->path());
        }

        if (!$dryRun && $config->get('output.html', true)) {
            $this->line('  Reporte:   ' . HtmlReport::make()->write($alerts, $summary));
        }

        if (!$dryRun && !$this->option('no-notify') && $alerts) {
            $result = (new NotificationDispatcher($config))->dispatch($alerts);

            if ($result['filtered'] > 0) {
                $channels = $result['sent'] ? implode(', ', array_keys($result['sent'])) : 'ningun canal activo';
                $this->line("  Notificado: {$result['filtered']} alerta(s) por {$channels}");
            }

            foreach ($result['errors'] as $error) {
                $this->warn('  Canal con error — ' . $error);
            }
        }

        // Un modulo caido no tumba la corrida, pero si cambia el codigo de salida
        // para que el cron o el supervisor lo note.
        return $summary['errors'] ? self::FAILURE : self::SUCCESS;
    }

    // ── Salida por consola ────────────────────────────────────────────────────

    private function renderConsole(array $alerts, array $summary): void
    {
        $this->newLine();

        if (!$alerts) {
            $this->info('Sin alertas nuevas.');
        }

        foreach ($alerts as $alert) {
            $line = $alert->summary();

            match (strtoupper($alert->severity)) {
                Severity::CRITICA, Severity::ALTA => $this->error($line),
                Severity::MEDIA                   => $this->warn($line),
                default                           => $this->line($line),
            };

            $this->line('    → ' . $alert->recommendation);
        }

        $this->newLine();
        $this->line(sprintf(
            'Tenants: %d | Alertas: %d | Repetidas omitidas: %d | %d ms',
            $summary['tenants'],
            count($alerts),
            $summary['suppressed'],
            $summary['duration_ms']
        ));

        foreach ($summary['errors'] as $error) {
            $this->warn(sprintf(
                '  Modulo con error: %s%s — %s',
                $error['module'],
                $error['tenant'] ? " ({$error['tenant']})" : '',
                $error['error']
            ));
        }
    }

    // ── Utilidades ────────────────────────────────────────────────────────────

    private function acceptIntegrity(): int
    {
        $config  = AgentConfig::load();
        $monitor = new IntegrityMonitor($state = FileStateStore::make());

        $total = $monitor->accept(
            (array) $config->get('web_attacks.integrity.files', []),
            (array) $config->get('web_attacks.integrity.globs', []),
        );

        $state->flush();

        $this->info("Linea base de integridad aceptada: {$total} archivo(s).");
        $this->line('Corre esto despues de cada despliegue legitimo.');

        return self::SUCCESS;
    }

    private function initConfig(): int
    {
        $path = AgentConfig::overridePath();
        $dir  = dirname($path);

        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        if (is_file($path)) {
            $this->warn("Ya existe: {$path}");
            $this->line('No se sobrescribe. Borralo a mano si quieres regenerarlo.');

            return self::SUCCESS;
        }

        file_put_contents($path, $this->configTemplate());

        $this->info("Creado: {$path}");
        $this->line('Editalo para ajustar umbrales, listas y horarios. No hace falta tocar codigo.');

        return self::SUCCESS;
    }

    private function configTemplate(): string
    {
        return <<<'JSON'
{
  // ─────────────────────────────────────────────────────────────────────────
  // Agente de Seguridad — configuración editable
  //
  // Solo necesitas escribir aquí lo que quieras CAMBIAR respecto a los valores
  // por defecto de config/security-agent.php. Todo lo que no aparezca aquí
  // conserva su valor por defecto.
  //
  // Las listas (entre corchetes) REEMPLAZAN a la lista por defecto entera.
  // Se permiten comentarios // para que el archivo siga siendo legible.
  //
  // Las credenciales NO van aquí: van en .env.
  // ─────────────────────────────────────────────────────────────────────────

  // Qué módulos corren. Ponlos en false para apagarlos sin tocar código.
  "modules": {
    "unauthorized_access": true,
    "web_attacks": true,
    "order_fraud": true,
    "catalog_anomalies": true,
    "marketplace_health": false,
    "work_schedule": false
  },

  // No repetir la misma alerta dentro de estas horas.
  "dedupe_hours": 24,

  // Solo se notifica de esta severidad para arriba: BAJA, MEDIA, ALTA, CRITICA.
  "notify": {
    "min_severity": "ALTA"
  },

  // ── Módulo 5: ingreso no autorizado ────────────────────────────────────────
  "unauthorized_access": {
    // Lista blanca de correos. Si la dejas vacía, se acepta a cualquier usuario
    // ACTIVO y no bloqueado de la tabla `users`. En cuanto tengas el censo real
    // de tu equipo, escríbelo aquí: es la detección más valiosa del módulo.
    "authorized_emails": [
      // "carlos@ebaemy.com",
      // "ventas@ebaemy.com"
    ],

    // Países desde los que se permite entrar (código ISO de 2 letras).
    "allowed_countries": ["PE"],

    // IPs internas que nunca generan alerta de "IP nueva" (tu oficina, la VPN).
    "trusted_ips": ["127.0.0.1", "::1"],

    "brute_force": {
      "failed_attempts": 5,
      "window_minutes": 15
    },
    "password_spray": {
      "distinct_users": 4,
      "window_minutes": 30
    },
    "success_after_failures": 3,
    "impossible_travel_hours": 4
  },

  // ── Módulo 3: ciberseguridad del servidor ──────────────────────────────────
  "web_attacks": {
    // IMPORTANTE: pon aquí la ruta REAL del access log de tu servidor.
    // Se usa la primera de la lista que exista y sea legible por el usuario
    // que corre PHP. Compruébalo en el servidor con:
    //     ls -l /usr/local/openresty/nginx/logs/access.log
    "access_log_paths": [
      "/usr/local/openresty/nginx/logs/access.log",
      "/var/log/openresty/access.log",
      "/var/log/nginx/access.log"
    ],

    // IPs que nunca generan alerta: tu oficina, tu monitoreo, tu CDN.
    "whitelist_ips": ["127.0.0.1", "::1"],

    "scanner_404": { "threshold": 30, "window_minutes": 15 },
    "login_brute_force": { "threshold": 20, "window_minutes": 15 },
    "rate_limit": { "requests_per_minute": 300 },

    // Archivos cuyo SHA-256 se vigila. Si cambian sin despliegue, es la firma
    // de un skimmer de tarjetas. Tras un despliegue legítimo ejecuta:
    //     php artisan security:scan --accept-integrity
    "integrity": {
      "enabled": true
    }
  },

  // ── Módulo 1: fraude en pedidos ────────────────────────────────────────────
  "order_fraud": {
    // Un pedido se alerta desde 35 puntos. Cada señal suma lo que dice
    // "scores" en config/security-agent.php.
    "thresholds": { "media": 35, "alta": 60, "critica": 85 },

    // Monto máximo normal de un pedido, en soles. Por encima, suma riesgo.
    "max_amount": 5000,

    // Cuántas veces la mediana de tus pedidos se considera "monto desmedido".
    "median_multiplier": 4,

    // Pedidos desde la misma IP en 24 h a partir de los cuales suma riesgo.
    "same_ip_orders": 3,

    // Pedidos del mismo cliente en pocas horas.
    "customer_velocity": { "orders": 3, "window_hours": 6 },

    // Dominios de correo desechable. Agrega los que veas aparecer.
    "disposable_domains": [
      "mailinator.com", "yopmail.com", "tempmail.com", "10minutemail.com",
      "guerrillamail.com", "trashmail.com", "sharklasers.com",
      "getnada.com", "maildrop.cc", "throwawaymail.com", "dispostable.com"
    ]
  },

  // ── Módulo 2: errores de catálogo ──────────────────────────────────────────
  "catalog_anomalies": {
    // Margen mínimo sobre la VENTA, en porcentaje. El min_margin_pct del
    // propio producto, si lo tiene, manda sobre este valor.
    "min_margin_pct": 10,

    // Variación de precio que se considera anormal, respecto al precio previo.
    "price_drop_pct": 30,
    "price_rise_pct": 50,

    // Diferencia máxima tolerada entre el precio de tienda y el de marketplace.
    "cross_channel_pct": 25,

    // Stock a partir del cual el producto se considera crítico.
    "critical_stock": 3,

    // IDs de productos que nunca deben alertar (promos permanentes, muestras).
    "ignore_item_ids": []
  },

  // ── Módulo 6: horarios (fase C) ────────────────────────────────────────────
  "work_schedule": {
    // Jornada por día. null = día no laborable.
    "schedule": {
      "mon": ["08:00", "19:00"],
      "tue": ["08:00", "19:00"],
      "wed": ["08:00", "19:00"],
      "thu": ["08:00", "19:00"],
      "fri": ["08:00", "19:00"],
      "sat": ["09:00", "13:00"],
      "sun": null
    },
    "tolerance_minutes": 15,

    // Feriados de Perú. Actualízalos cada año.
    "holidays": [
      "2026-01-01", "2026-04-02", "2026-04-03", "2026-05-01",
      "2026-06-07", "2026-06-29", "2026-07-23", "2026-07-28",
      "2026-07-29", "2026-08-06", "2026-08-30", "2026-10-08",
      "2026-11-01", "2026-12-08", "2026-12-09", "2026-12-25"
    ],

    // Turnos especiales por usuario. Mismo formato que "schedule".
    "user_exceptions": {
      // "nocturno@ebaemy.com": { "mon": ["22:00", "06:00"] }
    }
  }
}
JSON;
    }
}
