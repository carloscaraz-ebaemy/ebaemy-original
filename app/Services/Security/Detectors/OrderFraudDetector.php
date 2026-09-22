<?php

namespace App\Services\Security\Detectors;

use App\Services\Security\Alert;
use App\Services\Security\Contracts\DetectorModule;
use App\Services\Security\ScanContext;
use App\Services\Security\Severity;
use App\Services\Security\Support\GeoLocator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Modulo 1 — Fraude en pedidos y pagos.
 *
 * Asigna a cada pedido reciente un puntaje de 0 a 100 sumando senales, y emite
 * una alerta cuando pasa el umbral configurado (MEDIA >=35, ALTA >=60,
 * CRITICA >=85). Un solo pedido nunca genera mas de una alerta: la alerta
 * lleva la lista de razones que sumaron.
 *
 * Solo lee `orders`. No anula, no retiene y no marca nada.
 */
class OrderFraudDetector implements DetectorModule
{
    /** Conexion de lectura de la corrida en curso. */
    private $db;

    public function key(): string   { return 'order_fraud'; }
    public function label(): string { return 'Fraude en pedidos y pagos'; }
    public function scope(): string { return 'tenant'; }

    public function detect(ScanContext $context): array
    {
        $this->db = $this->resolveConnection($context);

        $cfg      = $context->config;
        $lookback = (int) $cfg->get('order_fraud.lookback_hours', 24);
        $since    = $context->now->subHours($lookback);

        $orders = $this->recentOrders($context, $since);

        if ($orders->isEmpty()) {
            return [];
        }

        $median = $this->medianTotal($context);
        $geo    = new GeoLocator(
            $context->state,
            (int) $cfg->get('unauthorized_access.geolocation.cache_days', 30),
            (bool) $cfg->get('unauthorized_access.geolocation.enabled', true),
        );

        // Contextos que necesitan mirar mas alla de la ventana del pedido.
        $ipCounts   = $this->countsBy($context, 'ip_address', $context->now->subHours(24));
        $cardOwners = $this->cardOwners($context, $context->now->subDays(30));

        $alerts = [];

        foreach ($orders as $order) {
            $assessment = $this->assess($order, $context, $median, $geo, $ipCounts, $cardOwners);

            $severity = Severity::fromScore(
                $assessment['score'],
                (array) $cfg->get('order_fraud.thresholds', [])
            );

            if ($severity === null) {
                continue;
            }

            $alerts[] = new Alert(
                module: $this->key(),
                type: 'pedido_sospechoso',
                severity: $severity,
                title: sprintf(
                    'Pedido %s con puntaje de riesgo %d/100 (S/ %s)',
                    $this->reference($order),
                    $assessment['score'],
                    number_format((float) $order->total, 2)
                ),
                recommendation: $this->recommendation($severity, $assessment['reasons']),
                evidence: [
                    'pedido'      => $this->reference($order),
                    'order_id'    => $order->id,
                    'total'       => (float) $order->total,
                    'puntaje'     => $assessment['score'],
                    'razones'     => $assessment['reasons'],
                    'cliente'     => $assessment['email'],
                    'ip'          => $order->ip_address,
                    'pais_ip'     => $assessment['ip_country'],
                    'pais_envio'  => $assessment['ship_country'],
                    'tarjeta'     => $order->card_last4 ? '****' . $order->card_last4 : null,
                    'creado'      => (string) $order->created_at,
                ],
                tenant: $context->tenant,
                dedupeKey: "{$context->scopeKey()}:order_fraud:{$order->id}",
            );
        }

        return $alerts;
    }

    // ── Puntaje ───────────────────────────────────────────────────────────────

    private function assess($order, ScanContext $context, ?float $median, GeoLocator $geo, array $ipCounts, array $cardOwners): array
    {
        $cfg     = $context->config;
        $scores  = (array) $cfg->get('order_fraud.scores', []);
        $score   = 0;
        $reasons = [];

        $customer = $this->customer($order);
        $email    = strtolower((string) ($customer['correo_electronico'] ?? ''));
        $total    = (float) $order->total;

        // 1. Monto muy por encima de lo habitual.
        $multiplier = (float) $cfg->get('order_fraud.median_multiplier', 4);
        if ($median !== null && $median > 0 && $total > $median * $multiplier) {
            $score += (int) ($scores['amount_over_median'] ?? 20);
            $reasons[] = sprintf(
                'El monto (S/ %s) supera %sx la mediana de pedidos (S/ %s)',
                number_format($total, 2),
                $multiplier,
                number_format($median, 2)
            );
        }

        // 2. Monto sobre el maximo configurado.
        $max = (float) $cfg->get('order_fraud.max_amount', 5000);
        if ($max > 0 && $total > $max) {
            $score += (int) ($scores['amount_over_max'] ?? 25);
            $reasons[] = sprintf('El monto supera el maximo configurado de S/ %s', number_format($max, 2));
        }

        // 3. Pais de la IP distinto al pais de envio.
        $ipCountry   = null;
        $shipCountry = strtoupper((string) ($customer['codigo_pais'] ?? ''));

        if ($order->ip_address) {
            $position  = $geo->locate($order->ip_address);
            $ipCountry = $position['country'];

            if ($ipCountry && $shipCountry && $ipCountry !== $shipCountry) {
                $score += (int) ($scores['ip_country_mismatch'] ?? 25);
                $reasons[] = "La IP esta en {$ipCountry} pero el envio va a {$shipCountry}";
            }
        }

        // 4. Correo de dominio desechable.
        $domain = $email && str_contains($email, '@') ? substr(strrchr($email, '@'), 1) : null;
        if ($domain && in_array($domain, array_map('strtolower', (array) $cfg->get('order_fraud.disposable_domains', [])), true)) {
            $score += (int) ($scores['disposable_email'] ?? 20);
            $reasons[] = "El correo usa un dominio desechable ({$domain})";
        }

        // 5. Varios pedidos desde la misma IP en 24 h.
        $ipThreshold = (int) $cfg->get('order_fraud.same_ip_orders', 3);
        $fromSameIp  = $order->ip_address ? ($ipCounts[$order->ip_address] ?? 0) : 0;
        if ($fromSameIp >= $ipThreshold) {
            $score += (int) ($scores['multi_order_same_ip'] ?? 15);
            $reasons[] = "{$fromSameIp} pedidos en 24 h desde la misma IP ({$order->ip_address})";
        }

        // 6. La misma tarjeta usada por clientes distintos.
        if ($order->card_fingerprint) {
            $owners = $cardOwners[$order->card_fingerprint] ?? [];

            if (count($owners) > 1) {
                $score += (int) ($scores['card_shared'] ?? 30);
                $reasons[] = sprintf(
                    'La tarjeta ****%s se uso con %d clientes distintos: %s',
                    $order->card_last4 ?? '????',
                    count($owners),
                    implode(', ', array_slice($owners, 0, 4))
                );
            }
        }

        // 7. Varios pedidos del mismo cliente en poco tiempo.
        $velocity = (array) $cfg->get('order_fraud.customer_velocity', []);
        $window   = (int) ($velocity['window_hours'] ?? 6);
        $limit    = (int) ($velocity['orders'] ?? 3);

        if ($email) {
            $recent = $this->customerOrderCount($context, $email, $order->created_at->subHours($window), $order->created_at);

            if ($recent >= $limit) {
                $score += (int) ($scores['customer_velocity'] ?? 15);
                $reasons[] = "{$recent} pedidos del mismo cliente en {$window} h";
            }
        }

        // 8. Compra de madrugada.
        [$from, $to] = (array) $cfg->get('order_fraud.night_hours', [0, 6]);
        $hour = (int) $order->created_at->format('G');
        if ($hour >= $from && $hour < $to) {
            $score += (int) ($scores['night_purchase'] ?? 10);
            $reasons[] = sprintf('Compra a las %02d:%02d, en horario de madrugada', $hour, (int) $order->created_at->format('i'));
        }

        return [
            'score'        => min(100, $score),
            'reasons'      => $reasons,
            'email'        => $email ?: null,
            'ip_country'   => $ipCountry,
            'ship_country' => $shipCountry ?: null,
        ];
    }

    private function recommendation(string $severity, array $reasons): string
    {
        $base = match ($severity) {
            Severity::CRITICA => 'NO despaches este pedido todavia. Llama al cliente al telefono del pedido y verifica la compra antes de preparar nada. Si el pago fue con tarjeta, no captures el cobro hasta confirmar: un contracargo se paga completo mas la comision.',
            Severity::ALTA    => 'Retén el despacho y verifica por telefono o WhatsApp antes de preparar. Comprueba que el nombre del pedido, el del documento y el de la tarjeta sean coherentes.',
            default           => 'Revisa el pedido antes de despachar. No hace falta detenerlo, pero conviene una mirada humana.',
        };

        return $base . ' Motivos detectados: ' . implode('; ', $reasons) . '.';
    }

    // ── Lectura ───────────────────────────────────────────────────────────────

    private function recentOrders(ScanContext $context, CarbonImmutable $since): Collection
    {
        return collect(
            $this->connection()
                ->table('orders')
                ->whereNull('deleted_at')
                ->where('created_at', '>=', $since)
                ->orderBy('created_at')
                ->limit(5000)
                ->get([
                    'id', 'external_id', 'number_document', 'customer', 'total',
                    'ip_address', 'card_last4', 'card_fingerprint', 'created_at',
                ])
        )->map(function ($row) {
            $row->created_at = CarbonImmutable::parse($row->created_at);
            return $row;
        });
    }

    /**
     * Mediana de los pedidos de los ultimos meses. Es mas robusta que el
     * promedio: un solo pedido enorme no mueve la referencia.
     */
    private function medianTotal(ScanContext $context): ?float
    {
        $days   = (int) $context->config->get('order_fraud.median_days', 90);
        $totals = $this->connection()
            ->table('orders')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $context->now->subDays($days))
            ->where('total', '>', 0)
            ->orderBy('total')
            ->pluck('total');

        $count = $totals->count();
        if ($count === 0) return null;

        $middle = (int) floor(($count - 1) / 2);

        return $count % 2
            ? (float) $totals[$middle]
            : ((float) $totals[$middle] + (float) $totals[$middle + 1]) / 2;
    }

    /** @return array<string,int> valor => cuantos pedidos */
    private function countsBy(ScanContext $context, string $column, CarbonImmutable $since): array
    {
        return $this->connection()
            ->table('orders')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $since)
            ->whereNotNull($column)
            ->groupBy($column)
            ->select($column, DB::raw('COUNT(*) as total'))
            ->pluck('total', $column)
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Huella de tarjeta => correos distintos que la usaron.
     *
     * @return array<string,array<int,string>>
     */
    private function cardOwners(ScanContext $context, CarbonImmutable $since): array
    {
        $rows = $this->connection()
            ->table('orders')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $since)
            ->whereNotNull('card_fingerprint')
            ->get(['card_fingerprint', 'customer']);

        $owners = [];

        foreach ($rows as $row) {
            $customer = $this->customer($row);
            $email    = strtolower((string) ($customer['correo_electronico'] ?? ''));

            if ($email === '') continue;

            $owners[$row->card_fingerprint][$email] = true;
        }

        return array_map(fn ($set) => array_keys($set), $owners);
    }

    private function customerOrderCount(ScanContext $context, string $email, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) $this->connection()
            ->table('orders')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$from, $to])
            ->where('customer', 'like', '%' . $email . '%')
            ->count();
    }

    // ── Utilidades ────────────────────────────────────────────────────────────

    private function customer($order): array
    {
        if (empty($order->customer)) return [];

        $decoded = is_string($order->customer) ? json_decode($order->customer, true) : $order->customer;

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * La referencia que se le muestra al operador es la del PEDIDO, nunca un
     * codigo interno de otro modulo.
     */
    private function reference($order): string
    {
        return (string) ($order->number_document ?: ('#' . $order->id));
    }

    private function connection()
    {
        return $this->db;
    }

    private function resolveConnection(ScanContext $context)
    {
        if ($context->tenant === null) {
            // Fuera de un tenant (tests, diagnostico) se usa la conexion normal.
            return DB::connection(config('database.default'));
        }

        $override = $context->config->get('read_connection');

        return DB::connection($override ?: 'tenant');
    }
}
