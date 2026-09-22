<?php

namespace App\Services\Security;

use App\Services\Security\Contracts\DetectorModule;
use App\Services\Security\State\FileStateStore;
use App\Services\Security\Support\AgentConfig;
use Carbon\CarbonImmutable;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\Log;

/**
 * Orquestador del agente de seguridad.
 *
 * Corre cada modulo habilitado de forma AISLADA: si uno falla, se registra el
 * error como parte del resultado y los demas siguen. El agente solo lee datos
 * y produce alertas; nunca bloquea, pausa ni modifica nada.
 */
class SecurityAgent
{
    /** @var DetectorModule[] */
    private array $detectors;

    private array $errors = [];

    public function __construct(?array $detectors = null)
    {
        $this->detectors = $detectors ?? [
            new Detectors\UnauthorizedAccessDetector(),
            new Detectors\WebAttackDetector(),
            new Detectors\OrderFraudDetector(),
            new Detectors\CatalogAnomalyDetector(),
            new Detectors\MarketplaceHealthDetector(),
            new Detectors\WorkScheduleDetector(),
        ];
    }

    /**
     * @param  array{tenant?:?string,module?:?string,dry_run?:bool}  $options
     * @return array{alerts:Alert[],suppressed:int,errors:array,tenants:int,started_at:string,duration_ms:int}
     */
    public function run(array $options = []): array
    {
        $startedAt = microtime(true);

        $config = AgentConfig::load();
        $state  = FileStateStore::make();
        $now    = CarbonImmutable::now($config->get('timezone', 'America/Lima'));

        $context = new ScanContext($now, $config, $state);

        $onlyModule = $options['module'] ?? null;
        $onlyTenant = $options['tenant'] ?? null;

        $raw     = [];
        $tenants = 0;

        foreach ($this->detectors as $detector) {
            if ($onlyModule && $detector->key() !== $onlyModule) continue;

            if (!$config->moduleEnabled($detector->key())) continue;

            $scope = $detector->scope();

            if (in_array($scope, ['system', 'both'], true)) {
                $raw = array_merge($raw, $this->runDetector($detector, $context));
            }

            if (in_array($scope, ['tenant', 'both'], true)) {
                $tenants = $this->forEachTenant($onlyTenant, function (Website $website, ?string $hostname) use ($detector, $context, &$raw) {
                    $tenantContext = $context->withTenant($website->uuid, $hostname);
                    $raw = array_merge($raw, $this->runDetector($detector, $tenantContext));
                });
            }
        }

        // Deduplicacion: la misma alerta no se repite dentro de la ventana.
        $window     = (int) $config->get('dedupe_hours', 24);
        $emitted    = [];
        $suppressed = 0;

        foreach ($raw as $alert) {
            if ($state->isDuplicate($alert->dedupeKey(), $window, $now)) {
                $suppressed++;
                continue;
            }

            $emitted[] = $alert;
        }

        usort($emitted, fn (Alert $a, Alert $b) => Severity::rank($b->severity) <=> Severity::rank($a->severity));

        if (empty($options['dry_run'])) {
            $state->flush();
        }

        return [
            'alerts'      => $emitted,
            'suppressed'  => $suppressed,
            'errors'      => $this->errors,
            'tenants'     => $tenants,
            'started_at'  => $now->toIso8601String(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    /** @return Alert[] */
    private function runDetector(DetectorModule $detector, ScanContext $context): array
    {
        try {
            return $detector->detect($context);
        } catch (\Throwable $e) {
            $this->errors[] = [
                'module' => $detector->key(),
                'tenant' => $context->tenant,
                'error'  => $e->getMessage(),
                'file'   => basename($e->getFile()) . ':' . $e->getLine(),
            ];

            Log::error('[SecurityAgent] Modulo con error, se continua con los demas.', [
                'module' => $detector->key(),
                'tenant' => $context->tenant,
                'error'  => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function forEachTenant(?string $onlyTenant, callable $callback): int
    {
        $environment = app(Environment::class);
        $previous    = $environment->tenant();
        $count       = 0;

        $query = Website::query();
        if ($onlyTenant) {
            $query->where('uuid', $onlyTenant);
        }

        try {
            $query->chunk(10, function ($websites) use ($environment, $callback, &$count) {
                foreach ($websites as $website) {
                    try {
                        $environment->tenant($website);
                        $hostname = optional($website->hostnames()->first())->fqdn;
                        $callback($website, $hostname);
                        $count++;
                    } catch (\Throwable $e) {
                        $this->errors[] = [
                            'module' => 'tenant',
                            'tenant' => $website->uuid,
                            'error'  => $e->getMessage(),
                            'file'   => basename($e->getFile()) . ':' . $e->getLine(),
                        ];
                    }
                }
            });
        } finally {
            // Dejamos la conexion como estaba, pase lo que pase.
            if ($previous) {
                $environment->tenant($previous);
            }
        }

        return $count;
    }
}
