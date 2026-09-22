<?php

namespace App\Services\Security\Detectors;

use App\Services\Security\Alert;
use App\Services\Security\Contracts\DetectorModule;
use App\Services\Security\ScanContext;
use App\Services\Security\Severity;
use App\Services\Security\Support\GeoLocator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Modulo 5 — Ingreso no autorizado.
 *
 * Lee `login_events` (la escribe App\Listeners\Security\RecordLoginEvent) y la
 * tabla `users` para saber quien tiene permitido entrar. No escribe nada.
 *
 * Siete deteccciones:
 *   1. Ingreso de un usuario que no esta en la lista de autorizados  CRITICA
 *   2. Ingreso exitoso tras varios fallos contra la misma cuenta     CRITICA
 *   3. Viaje imposible                                               CRITICA
 *   4. Fuerza bruta contra una cuenta                                ALTA
 *   5. Rociado de contrasenas desde una IP                           ALTA
 *   6. Ingreso desde un pais no permitido                            ALTA
 *   7. Ingreso desde una IP nueva para ese usuario                   MEDIA
 */
class UnauthorizedAccessDetector implements DetectorModule
{
    private const DEFAULT_LOOKBACK_MINUTES = 60;

    public function key(): string   { return 'unauthorized_access'; }
    public function label(): string { return 'Ingreso no autorizado'; }
    public function scope(): string { return 'both'; }

    public function detect(ScanContext $context): array
    {
        $cfg   = $context->config;
        $scope = $context->scopeKey();

        $lookback = (int) $cfg->get('unauthorized_access.lookback_minutes', self::DEFAULT_LOOKBACK_MINUTES);
        $since    = $context->now->subMinutes(max($lookback, 15));

        $events = $this->recentEvents($context, $since);

        if ($events->isEmpty()) {
            return [];
        }

        $geo = new GeoLocator(
            $context->state,
            (int) $cfg->get('unauthorized_access.geolocation.cache_days', 30),
            (bool) $cfg->get('unauthorized_access.geolocation.enabled', true),
        );

        // Primera corrida para este ambito: aprendemos las IPs historicas sin
        // alertar, si no el primer escaneo dispararia una alerta por usuario.
        $seeded = (bool) $context->state->get('known_ips', "{$scope}.seeded", false);
        if (!$seeded) {
            $this->seedKnownIps($context, $scope);
        }

        $alerts = array_merge(
            $this->unauthorizedUsers($context, $events),
            $this->successAfterFailures($context, $events),
            $this->bruteForce($context, $events),
            $this->passwordSpray($context, $events),
            $this->geoRules($context, $events, $geo, $seeded),
        );

        return $alerts;
    }

    // ── 1. Usuario fuera de la lista de autorizados ──────────────────────────

    private function unauthorizedUsers(ScanContext $context, Collection $events): array
    {
        $authorized = $this->authorizedEmails($context);
        $alerts     = [];

        foreach ($events->where('result', 'success') as $event) {
            $email = strtolower((string) $event->email);
            if ($email === '') continue;

            if ($authorized !== null && !in_array($email, $authorized, true)) {
                $alerts[] = new Alert(
                    module: $this->key(),
                    type: 'usuario_no_autorizado',
                    severity: Severity::CRITICA,
                    title: "Ingreso exitoso de un usuario fuera de la lista de autorizados: {$email}",
                    recommendation: 'Verifica de inmediato si la cuenta es legitima. Si no lo es: desactiva el usuario en Configuracion → Usuarios, fuerza el cierre de sesion y cambia la contrasena. Revisa `audit_logs` para ver que hizo esa cuenta.',
                    evidence: [
                        'email'      => $email,
                        'ip'         => $event->ip_address,
                        'fecha'      => (string) $event->created_at,
                        'user_agent' => $event->user_agent,
                        'guard'      => $event->guard,
                    ],
                    tenant: $context->tenant,
                    dedupeKey: "{$context->scopeKey()}:unauthorized_user:{$email}",
                );
            }
        }

        return $alerts;
    }

    /**
     * Lista blanca efectiva. Si el operador no configuro ninguna, se usa la
     * tabla `users`: cualquier usuario activo y no bloqueado vale. Devuelve
     * null solo si no hay forma de saberlo (entonces no se alerta).
     */
    private function authorizedEmails(ScanContext $context): ?array
    {
        $configured = (array) $context->config->get('unauthorized_access.authorized_emails', []);

        if ($configured) {
            return array_map(fn ($e) => strtolower(trim((string) $e)), $configured);
        }

        try {
            $rows = $this->connection($context)
                ->table('users')
                ->where('active', 1)
                ->where(function ($q) { $q->where('locked', 0)->orWhereNull('locked'); })
                ->pluck('email');

            return $rows->map(fn ($e) => strtolower((string) $e))->filter()->values()->all();
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ── 2. Ingreso exitoso tras varios fallos ────────────────────────────────

    private function successAfterFailures(ScanContext $context, Collection $events): array
    {
        $threshold = (int) $context->config->get('unauthorized_access.success_after_failures', 3);
        $alerts    = [];

        foreach ($events->where('result', 'success') as $success) {
            $email = strtolower((string) $success->email);
            if ($email === '') continue;

            $failuresBefore = $events
                ->where('result', 'failed')
                ->filter(fn ($e) => strtolower((string) $e->email) === $email
                    && $e->created_at < $success->created_at
                    && $e->created_at >= $success->created_at->copy()->subMinutes(30))
                ->count();

            if ($failuresBefore >= $threshold) {
                $alerts[] = new Alert(
                    module: $this->key(),
                    type: 'acceso_tras_fallos',
                    severity: Severity::CRITICA,
                    title: "Ingreso exitoso en {$email} despues de {$failuresBefore} intentos fallidos",
                    recommendation: 'Trata la cuenta como comprometida hasta demostrar lo contrario: cambia la contrasena, cierra todas las sesiones y revisa en `audit_logs` que se hizo despues de ese ingreso (precios, exportaciones, usuarios nuevos).',
                    evidence: [
                        'email'           => $email,
                        'fallos_previos'  => $failuresBefore,
                        'ip'              => $success->ip_address,
                        'fecha_ingreso'   => (string) $success->created_at,
                    ],
                    tenant: $context->tenant,
                    dedupeKey: "{$context->scopeKey()}:success_after_fail:{$email}:" . $success->created_at->format('YmdH'),
                );
            }
        }

        return $alerts;
    }

    // ── 4. Fuerza bruta contra una cuenta ────────────────────────────────────

    private function bruteForce(ScanContext $context, Collection $events): array
    {
        $attempts = (int) $context->config->get('unauthorized_access.brute_force.failed_attempts', 5);
        $window   = (int) $context->config->get('unauthorized_access.brute_force.window_minutes', 15);
        $since    = $context->now->subMinutes($window);
        $alerts   = [];

        $failed = $events->filter(fn ($e) => $e->result === 'failed' && $e->created_at >= $since);

        foreach ($failed->groupBy(fn ($e) => strtolower((string) $e->email)) as $email => $group) {
            if ($email === '' || $group->count() < $attempts) continue;

            $ips = $group->pluck('ip_address')->filter()->unique()->values()->all();

            $alerts[] = new Alert(
                module: $this->key(),
                type: 'fuerza_bruta',
                severity: Severity::ALTA,
                title: "{$group->count()} intentos fallidos contra {$email} en {$window} minutos",
                recommendation: "Bloquea temporalmente las IPs " . implode(', ', array_slice($ips, 0, 5)) . " en el firewall y avisa al titular de la cuenta. Si la contrasena es debil, forzar su cambio. Considera activar segundo factor para esa cuenta.",
                evidence: [
                    'email'           => $email,
                    'intentos'        => $group->count(),
                    'ventana_minutos' => $window,
                    'ips'             => $ips,
                ],
                tenant: $context->tenant,
                dedupeKey: "{$context->scopeKey()}:brute_force:{$email}",
            );
        }

        return $alerts;
    }

    // ── 5. Rociado de contrasenas ────────────────────────────────────────────

    private function passwordSpray(ScanContext $context, Collection $events): array
    {
        $distinct = (int) $context->config->get('unauthorized_access.password_spray.distinct_users', 4);
        $window   = (int) $context->config->get('unauthorized_access.password_spray.window_minutes', 30);
        $since    = $context->now->subMinutes($window);
        $alerts   = [];

        $failed = $events->filter(fn ($e) => $e->result === 'failed' && $e->created_at >= $since && $e->ip_address);

        foreach ($failed->groupBy('ip_address') as $ip => $group) {
            $users = $group->pluck('email')->filter()->map(fn ($e) => strtolower((string) $e))->unique()->values();

            if ($users->count() < $distinct) continue;

            $alerts[] = new Alert(
                module: $this->key(),
                type: 'rociado_contrasenas',
                severity: Severity::ALTA,
                title: "La IP {$ip} probo {$users->count()} usuarios distintos en {$window} minutos",
                recommendation: "Bloquea {$ip} en el firewall del servidor. Es un rociado de contrasenas: el atacante prueba una contrasena comun contra muchas cuentas. Revisa si alguna de esas cuentas tuvo despues un ingreso exitoso.",
                evidence: [
                    'ip'              => $ip,
                    'usuarios'        => $users->all(),
                    'intentos'        => $group->count(),
                    'ventana_minutos' => $window,
                ],
                tenant: $context->tenant,
                dedupeKey: "{$context->scopeKey()}:password_spray:{$ip}",
            );
        }

        return $alerts;
    }

    // ── 3, 6 y 7: reglas geograficas ─────────────────────────────────────────

    private function geoRules(ScanContext $context, Collection $events, GeoLocator $geo, bool $seeded): array
    {
        $cfg        = $context->config;
        $scope      = $context->scopeKey();
        $allowed    = array_map('strtoupper', (array) $cfg->get('unauthorized_access.allowed_countries', ['PE']));
        $trustedIps = (array) $cfg->get('unauthorized_access.trusted_ips', []);
        $travelHrs  = (int) $cfg->get('unauthorized_access.impossible_travel_hours', 4);
        $alerts     = [];

        $successes = $events->where('result', 'success')->sortBy('created_at');

        foreach ($successes as $event) {
            $email = strtolower((string) $event->email);
            $ip    = (string) $event->ip_address;

            if ($email === '' || $ip === '' || in_array($ip, $trustedIps, true)) continue;

            $position = $geo->locate($ip);

            // 6. Pais no permitido.
            if ($position['country'] && $allowed && !in_array($position['country'], $allowed, true)) {
                $alerts[] = new Alert(
                    module: $this->key(),
                    type: 'pais_no_permitido',
                    severity: Severity::ALTA,
                    title: "Ingreso de {$email} desde {$position['country']}, fuera de los paises permitidos (" . implode(', ', $allowed) . ')',
                    recommendation: 'Confirma con el usuario si esta de viaje. Si no lo esta, cambia la contrasena y cierra sesiones. Si viaja con frecuencia, agrega su pais a `allowed_countries` o creale una excepcion en el archivo de configuracion.',
                    evidence: [
                        'email'  => $email,
                        'ip'     => $ip,
                        'pais'   => $position['country'],
                        'ciudad' => $position['city'],
                        'fecha'  => (string) $event->created_at,
                    ],
                    tenant: $context->tenant,
                    dedupeKey: "{$scope}:country:{$email}:{$position['country']}",
                );
            }

            // 7. IP nueva para ese usuario.
            $knownKey = "{$scope}.ips.{$email}";
            $known    = (array) $context->state->get('known_ips', $knownKey, []);

            if (!in_array($ip, $known, true)) {
                if ($seeded && !$geo->isPrivate($ip)) {
                    $alerts[] = new Alert(
                        module: $this->key(),
                        type: 'ip_nueva',
                        severity: Severity::MEDIA,
                        title: "{$email} ingreso desde una IP nunca vista: {$ip}",
                        recommendation: 'Confirma con el usuario que fue el. Si no reconoce el acceso, cambia la contrasena y revisa `audit_logs` de esa sesion. Si es su nueva conexion habitual, no hace falta hacer nada: el agente la dara por conocida a partir de ahora.',
                        evidence: [
                            'email'  => $email,
                            'ip'     => $ip,
                            'pais'   => $position['country'],
                            'ciudad' => $position['city'],
                            'fecha'  => (string) $event->created_at,
                        ],
                        tenant: $context->tenant,
                        dedupeKey: "{$scope}:new_ip:{$email}:{$ip}",
                    );
                }

                $context->state->push('known_ips', $knownKey, $ip, 200);
            }

            // 3. Viaje imposible.
            $lastKey  = "{$scope}.last_location.{$email}";
            $previous = $context->state->get('known_ips', $lastKey);

            if ($position['country']) {
                if (is_array($previous) && !empty($previous['country']) && $previous['country'] !== $position['country']) {
                    $elapsed = $event->created_at->getTimestamp() - (int) ($previous['at'] ?? 0);

                    if ($elapsed > 0 && $elapsed < $travelHrs * 3600) {
                        $alerts[] = new Alert(
                            module: $this->key(),
                            type: 'viaje_imposible',
                            severity: Severity::CRITICA,
                            title: sprintf(
                                '%s ingreso desde %s y desde %s con %s horas de diferencia',
                                $email,
                                $previous['country'],
                                $position['country'],
                                round($elapsed / 3600, 1)
                            ),
                            recommendation: 'Es fisicamente imposible: una de las dos sesiones no es del usuario. Cambia la contrasena de inmediato, cierra todas las sesiones y revisa `audit_logs` por acciones sensibles en esa ventana.',
                            evidence: [
                                'email'           => $email,
                                'pais_anterior'   => $previous['country'],
                                'pais_actual'     => $position['country'],
                                'horas'           => round($elapsed / 3600, 2),
                                'ip_actual'       => $ip,
                                'fecha'           => (string) $event->created_at,
                            ],
                            tenant: $context->tenant,
                            dedupeKey: "{$scope}:impossible_travel:{$email}:" . $event->created_at->format('Ymd'),
                        );
                    }
                }

                $context->state->put('known_ips', $lastKey, [
                    'country' => $position['country'],
                    'ip'      => $ip,
                    'at'      => $event->created_at->getTimestamp(),
                ]);
            }
        }

        return $alerts;
    }

    // ── Lectura ───────────────────────────────────────────────────────────────

    private function recentEvents(ScanContext $context, CarbonImmutable $since): Collection
    {
        try {
            return collect(
                $this->connection($context)
                    ->table('login_events')
                    ->where('created_at', '>=', $since)
                    ->orderBy('created_at')
                    ->limit(20000)
                    ->get()
            )->map(function ($row) {
                $row->created_at = CarbonImmutable::parse($row->created_at);
                return $row;
            });
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'No se pudo leer `login_events`. ¿Corriste las migraciones? Detalle: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private function seedKnownIps(ScanContext $context, string $scope): void
    {
        try {
            $rows = $this->connection($context)
                ->table('login_events')
                ->where('result', 'success')
                ->whereNotNull('email')
                ->whereNotNull('ip_address')
                ->orderByDesc('created_at')
                ->limit(20000)
                ->get(['email', 'ip_address']);

            foreach ($rows as $row) {
                $email = strtolower((string) $row->email);
                if ($email === '') continue;

                $context->state->push('known_ips', "{$scope}.ips.{$email}", (string) $row->ip_address, 200);
            }
        } catch (\Throwable $e) {
            // Sin historial que aprender: seguimos igual.
        }

        $context->state->put('known_ips', "{$scope}.seeded", true);
    }

    private function connection(ScanContext $context)
    {
        $override = $context->config->get('read_connection');

        if ($override && $context->tenant === null) {
            return \Illuminate\Support\Facades\DB::connection($override);
        }

        return \Illuminate\Support\Facades\DB::connection(
            $context->tenant === null ? config('database.default') : 'tenant'
        );
    }
}
