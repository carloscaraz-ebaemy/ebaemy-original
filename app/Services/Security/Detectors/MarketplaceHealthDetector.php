<?php

namespace App\Services\Security\Detectors;

use App\Services\Security\Alert;
use App\Services\Security\Contracts\DetectorModule;
use App\Services\Security\ScanContext;
use App\Services\Security\Severity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Modulo 4 — Salud de las cuentas en marketplaces.
 *
 * Dos fuentes, segun lo que cada plataforma exponga:
 *
 *  - MercadoLibre publica reputacion y metricas oficiales. Se consultan por API
 *    (solo lectura) y se usa su semaforo: rojo CRITICA, naranja ALTA,
 *    amarillo MEDIA.
 *  - Falabella (Saga) NO expone una API de salud de cuenta. Sus metricas se
 *    derivan de lo que tenemos en casa: `marketplace_orders` y
 *    `marketplace_products`.
 *
 * Amazon y Ripley todavia no existen como integracion en este sistema. Cuando
 * existan, basta con que su plataforma aparezca en `marketplace_channels`: las
 * metricas locales funcionan igual sin tocar este archivo.
 */
class MarketplaceHealthDetector implements DetectorModule
{
    /** Semaforo de reputacion de MercadoLibre. */
    private const REPUTATION = [
        '1_red'     => [Severity::CRITICA, 'roja'],
        '2_orange'  => [Severity::ALTA,    'naranja'],
        '3_yellow'  => [Severity::MEDIA,   'amarilla'],
        '4_light_green' => [null, 'verde claro'],
        '5_green'   => [null, 'verde'],
    ];

    private $db;

    public function key(): string   { return 'marketplace_health'; }
    public function label(): string { return 'Salud de cuentas en marketplaces'; }
    public function scope(): string { return 'tenant'; }

    public function detect(ScanContext $context): array
    {
        $this->db = $this->resolveConnection($context);

        try {
            $channels = $this->db->table('marketplace_channels')
                ->where('status', 'active')
                ->get(['id', 'platform', 'name', 'last_error_at', 'last_error_message', 'last_sync_at']);
        } catch (\Throwable $e) {
            // Tenant sin el modulo de marketplaces instalado.
            return [];
        }

        $alerts = [];

        foreach ($channels as $channel) {
            $metrics = $this->metricsFor($channel, $context);

            if ($metrics['error']) {
                $alerts[] = $this->connectionAlert($channel, $metrics['error'], $context);
                continue;
            }

            // La API no respondio: seguimos evaluando con las metricas locales,
            // pero hay que decirlo — sin ella no hay semaforo oficial.
            if (!empty($metrics['api_error'])) {
                $alerts[] = $this->connectionAlert($channel, $metrics['api_error'], $context);
            }

            $alerts = array_merge(
                $alerts,
                $this->reputationAlert($channel, $metrics, $context),
                $this->limitAlerts($channel, $metrics, $context),
                $this->degradationAlerts($channel, $metrics, $context),
            );

            $this->rememberMetrics($channel, $metrics, $context);
        }

        return $alerts;
    }

    // ── Origen de las metricas ───────────────────────────────────────────────

    private function metricsFor($channel, ScanContext $context): array
    {
        $local = $this->localMetrics($channel, $context);

        if (strtolower((string) $channel->platform) !== 'mercadolibre') {
            return $local;
        }

        $health = $this->mercadoLibreHealth($channel);

        if (!$health['ok']) {
            // La API fallo: seguimos con lo que sabemos localmente, pero lo
            // decimos, porque sin ella no hay semaforo oficial.
            return array_merge($local, ['api_error' => $health['error']]);
        }

        return array_merge($local, array_filter([
            'level'             => $health['level'],
            'claims_pct'        => $health['metrics']['claims_pct'],
            'cancellations_pct' => $health['metrics']['cancellations_pct'],
            'late_shipment_pct' => $health['metrics']['late_shipment_pct'],
            'paused_listings'   => $health['paused'],
            'unanswered_questions' => $health['unanswered'],
        ], fn ($v) => $v !== null), ['source' => 'api']);
    }

    private function mercadoLibreHealth($channel): array
    {
        try {
            $model = \App\Models\Tenant\MarketplaceChannel::find($channel->id);

            if (!$model) {
                return ['ok' => false, 'error' => 'No se pudo cargar el canal.'];
            }

            return (new \App\Services\Marketplace\MercadoLibreService($model))->getSellerHealth();
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Metricas derivadas de nuestras propias tablas. Es lo unico disponible
     * para Falabella, y el respaldo cuando la API de ML no responde.
     */
    private function localMetrics($channel, ScanContext $context): array
    {
        $since = $context->now->subDays((int) $context->config->get('marketplace_health.window_days', 30));

        $orders = $this->db->table('marketplace_orders')
            ->where('channel_id', $channel->id)
            ->where('ordered_at', '>=', $since)
            ->get(['status', 'ordered_at', 'processed_at']);

        $total     = $orders->count();
        $cancelled = $orders->filter(fn ($o) => in_array(strtolower((string) $o->status), ['canceled', 'cancelled', 'anulado'], true))->count();

        $lateHours = (int) $context->config->get('marketplace_health.dispatch_sla_hours', 48);
        $late      = $orders->filter(function ($o) use ($lateHours, $context) {
            if (!$o->ordered_at) return false;

            $ordered = CarbonImmutable::parse($o->ordered_at);
            $end     = $o->processed_at ? CarbonImmutable::parse($o->processed_at) : $context->now;

            return $ordered->diffInHours($end) > $lateHours;
        })->count();

        $paused = (int) $this->db->table('marketplace_products')
            ->where('channel_id', $channel->id)
            ->whereIn('sync_status', ['paused', 'rejected', 'error'])
            ->count();

        return [
            'error'                => null,
            'api_error'            => null,
            'source'               => 'local',
            'level'                => null,
            'pedidos'              => $total,
            'claims_pct'           => null,        // ninguna plataforma local lo expone
            'cancellations_pct'    => $total ? round($cancelled / $total * 100, 2) : 0.0,
            'late_shipment_pct'    => $total ? round($late / $total * 100, 2) : 0.0,
            'paused_listings'      => $paused,
            'unanswered_questions' => null,
        ];
    }

    // ── Alertas ───────────────────────────────────────────────────────────────

    private function connectionAlert($channel, string $error, ScanContext $context): Alert
    {
        return new Alert(
            module: $this->key(),
            type: 'canal_sin_conexion',
            severity: Severity::ALTA,
            title: "No se pudo consultar la salud de {$channel->name} ({$channel->platform})",
            recommendation: 'Revisa las credenciales del canal en Marketplaces → Canales. Si el token expiro, renuevalo: mientras tanto no se sincroniza stock ni precio, y el catalogo publicado se va quedando desfasado.',
            evidence: ['canal' => $channel->name, 'plataforma' => $channel->platform, 'error' => $error],
            tenant: $context->tenant,
            dedupeKey: "{$context->scopeKey()}:mp_health:connection:{$channel->id}",
        );
    }

    private function reputationAlert($channel, array $metrics, ScanContext $context): array
    {
        if (empty($metrics['level'])) {
            return [];
        }

        [$severity, $color] = self::REPUTATION[$metrics['level']] ?? [null, $metrics['level']];

        if ($severity === null) {
            return [];
        }

        return [new Alert(
            module: $this->key(),
            type: 'reputacion_en_riesgo',
            severity: $severity,
            title: "La reputacion de {$channel->name} esta en {$color}",
            recommendation: $severity === Severity::CRITICA
                ? 'Con reputacion roja MercadoLibre baja tu exposicion en las busquedas y puede suspender la cuenta. Atiende hoy los reclamos abiertos, responde las preguntas pendientes y despacha lo atrasado. Es lo primero de la lista.'
                : 'Estas a un escalon de perder exposicion. Mira cual de las tres metricas (reclamos, cancelaciones, demoras) te esta arrastrando y corrige esa: la reputacion se recalcula sobre los ultimos 60 dias, asi que mejora sola si dejas de sumar casos.',
            evidence: [
                'canal'      => $channel->name,
                'nivel'      => $metrics['level'],
                'reclamos'   => $metrics['claims_pct'],
                'cancelaciones' => $metrics['cancellations_pct'],
                'demoras'    => $metrics['late_shipment_pct'],
            ],
            tenant: $context->tenant,
            dedupeKey: "{$context->scopeKey()}:mp_health:reputation:{$channel->id}:{$metrics['level']}",
        )];
    }

    private function limitAlerts($channel, array $metrics, ScanContext $context): array
    {
        $limits = (array) $context->config->get('marketplace_health.limits', []);

        $checks = [
            'claims_pct'           => ['Reclamos', '%', 'Revisa los reclamos abiertos uno por uno: la mayoria se cierran respondiendo el mismo dia.'],
            'cancellations_pct'    => ['Cancelaciones', '%', 'Casi siempre es stock publicado que no existe. Cruza esto con las alertas de catalogo del agente.'],
            'late_shipment_pct'    => ['Demoras de despacho', '%', 'Revisa el cuello de botella en preparacion. Una demora sistematica hunde la reputacion mas rapido que un reclamo suelto.'],
            'paused_listings'      => ['Publicaciones pausadas', '', 'Una publicacion pausada no vende. Mira el motivo en Marketplaces → Productos: suele ser stock en cero o un rechazo de calidad.'],
            'unanswered_questions' => ['Preguntas sin responder', '', 'Responder tarde cuenta en la reputacion igual que un reclamo. Contestalas hoy.'],
        ];

        $alerts = [];

        foreach ($checks as $key => [$label, $unit, $advice]) {
            $value = $metrics[$key] ?? null;
            $limit = $limits[$key] ?? null;

            if ($value === null || $limit === null || $value <= $limit) continue;

            $alerts[] = new Alert(
                module: $this->key(),
                type: 'metrica_sobre_limite',
                severity: Severity::ALTA,
                title: sprintf('%s en %s: %s%s (limite %s%s)', $label, $channel->name, $value, $unit, $limit, $unit),
                recommendation: $advice,
                evidence: [
                    'canal'   => $channel->name,
                    'metrica' => $label,
                    'valor'   => $value,
                    'limite'  => $limit,
                    'origen'  => $metrics['source'],
                ],
                tenant: $context->tenant,
                dedupeKey: "{$context->scopeKey()}:mp_health:limit:{$channel->id}:{$key}",
            );
        }

        return $alerts;
    }

    /** Empeoramiento respecto a la revision anterior. */
    private function degradationAlerts($channel, array $metrics, ScanContext $context): array
    {
        $threshold = (float) $context->config->get('marketplace_health.degradation_pct', 30);
        $previous  = (array) $context->state->get('marketplace_health', $this->stateKey($channel, $context), []);

        if (!$previous) {
            return [];
        }

        $worse = [];

        foreach (['claims_pct', 'cancellations_pct', 'late_shipment_pct', 'paused_listings', 'unanswered_questions'] as $key) {
            $now  = $metrics[$key] ?? null;
            $then = $previous[$key] ?? null;

            if ($now === null || $then === null || $then <= 0) continue;

            $change = (($now - $then) / $then) * 100;

            if ($change >= $threshold) {
                $worse[$key] = ['antes' => $then, 'ahora' => $now, 'empeoro' => round($change, 1) . '%'];
            }
        }

        if (!$worse) {
            return [];
        }

        return [new Alert(
            module: $this->key(),
            type: 'metrica_empeorando',
            severity: Severity::ALTA,
            title: sprintf('%d metrica(s) de %s empeoraron mas de %s%% desde la ultima revision', count($worse), $channel->name, $threshold),
            recommendation: 'Todavia puede que estes dentro del limite, pero la tendencia va en la direccion equivocada. Es mucho mas barato corregir ahora que cuando el semaforo ya cambio de color.',
            evidence: ['canal' => $channel->name, 'metricas' => $worse],
            tenant: $context->tenant,
            dedupeKey: "{$context->scopeKey()}:mp_health:degradation:{$channel->id}:" . implode(',', array_keys($worse)),
        )];
    }

    private function rememberMetrics($channel, array $metrics, ScanContext $context): void
    {
        $context->state->put('marketplace_health', $this->stateKey($channel, $context), array_merge(
            array_intersect_key($metrics, array_flip([
                'claims_pct', 'cancellations_pct', 'late_shipment_pct',
                'paused_listings', 'unanswered_questions', 'level',
            ])),
            ['at' => $context->now->toIso8601String()]
        ));
    }

    private function stateKey($channel, ScanContext $context): string
    {
        return "{$context->scopeKey()}.channel.{$channel->id}";
    }

    private function resolveConnection(ScanContext $context)
    {
        if ($context->tenant === null) {
            return DB::connection(config('database.default'));
        }

        $override = $context->config->get('read_connection');

        return DB::connection($override ?: 'tenant');
    }
}
