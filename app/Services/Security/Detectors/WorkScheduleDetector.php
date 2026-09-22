<?php

namespace App\Services\Security\Detectors;

use App\Services\Security\Alert;
use App\Services\Security\Contracts\DetectorModule;
use App\Services\Security\ScanContext;
use App\Services\Security\Severity;
use App\Services\Security\Support\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Modulo 6 — Horarios de trabajo.
 *
 * Junta la actividad del personal de tres fuentes que ya existen y la contrasta
 * con la jornada configurada:
 *
 *   - `login_events`       entradas y salidas del sistema
 *   - `item_price_history` cambios de precio y de costo, con quien los hizo
 *   - `audit_logs`         acciones sensibles instrumentadas
 *
 * Emite tres cosas: actividad fuera de horario agrupada por usuario y dia, una
 * rafaga de acciones sensibles concentradas en una hora, y el reporte de
 * jornada del dia. El reporte va en severidad BAJA a proposito: sirve para
 * consultarlo, no para despertar a nadie.
 */
class WorkScheduleDetector implements DetectorModule
{
    private $db;

    public function key(): string   { return 'work_schedule'; }
    public function label(): string { return 'Horarios de trabajo'; }
    public function scope(): string { return 'tenant'; }

    public function detect(ScanContext $context): array
    {
        $this->db = $this->resolveConnection($context);

        $schedule = WorkSchedule::fromConfig($context->config);
        $since    = $context->now->subHours((int) $context->config->get('work_schedule.lookback_hours', 24));

        $events = $this->collectEvents($context, $since);

        if ($events->isEmpty()) {
            return [];
        }

        return array_merge(
            $this->outsideHours($events, $schedule, $context),
            $this->sensitiveBursts($events, $context),
            $this->dailyReport($events, $schedule, $context),
        );
    }

    // ── Actividad fuera de horario ───────────────────────────────────────────

    private function outsideHours(Collection $events, WorkSchedule $schedule, ScanContext $context): array
    {
        $groups = [];

        foreach ($events as $event) {
            $reason = $schedule->reasonOutside($event['at'], $event['email']);
            if ($reason === null) continue;

            $key = $event['email'] . '|' . $event['at']->format('Y-m-d');

            $groups[$key]['email']       = $event['email'];
            $groups[$key]['dia']         = $event['at']->format('Y-m-d');
            $groups[$key]['motivo']      = $reason;
            $groups[$key]['total']       = ($groups[$key]['total'] ?? 0) + 1;
            $groups[$key]['sensibles']   = ($groups[$key]['sensibles'] ?? 0) + ($event['sensitive'] ? 1 : 0);
            $groups[$key]['acciones'][]  = [
                'hora'   => $event['at']->format('H:i'),
                'accion' => $event['action'],
                'origen' => $event['source'],
            ];
        }

        $alerts = [];

        foreach ($groups as $group) {
            $sensitive = $group['sensibles'] > 0;

            $alerts[] = new Alert(
                module: $this->key(),
                type: $sensitive ? 'actividad_sensible_fuera_de_horario' : 'actividad_fuera_de_horario',
                severity: $sensitive ? Severity::ALTA : Severity::MEDIA,
                title: sprintf(
                    '%s registro %d accion(es) fuera de horario el %s (%s)%s',
                    $group['email'],
                    $group['total'],
                    $group['dia'],
                    $group['motivo'],
                    $sensitive ? " — {$group['sensibles']} de ellas sensibles" : ''
                ),
                recommendation: $sensitive
                    ? 'Hubo cambios de precio, borrados o cambios de permisos fuera de la jornada. Pregunta al usuario que estaba haciendo y contrastalo con las alertas de ingreso no autorizado de esa misma franja: si la cuenta esta comprometida, este es el momento en que el atacante actuo.'
                    : 'Puede ser alguien adelantando trabajo, o puede ser una sesion que no es suya. Confirmalo con la persona. Si trabaja en turno distinto de forma habitual, dale una excepcion en `work_schedule.user_exceptions` para dejar de recibir este aviso.',
                evidence: [
                    'usuario'   => $group['email'],
                    'dia'       => $group['dia'],
                    'motivo'    => $group['motivo'],
                    'total'     => $group['total'],
                    'sensibles' => $group['sensibles'],
                    'acciones'  => array_slice($group['acciones'], 0, 25),
                ],
                tenant: $context->tenant,
                dedupeKey: "{$context->scopeKey()}:schedule:outside:{$group['email']}:{$group['dia']}",
            );
        }

        return $alerts;
    }

    // ── Rafaga de acciones sensibles ─────────────────────────────────────────

    private function sensitiveBursts(Collection $events, ScanContext $context): array
    {
        $config = (array) $context->config->get('work_schedule.sensitive_burst', []);
        $limit  = (int) ($config['actions'] ?? 20);
        $window = (int) ($config['window_minutes'] ?? 60);

        $alerts = [];

        foreach ($events->where('sensitive', true)->groupBy('email') as $email => $group) {
            $sorted = $group->sortBy(fn ($e) => $e['at']->getTimestamp())->values();
            $peak   = 0;
            $peakAt = null;

            foreach ($sorted as $i => $event) {
                $end   = $event['at']->addMinutes($window);
                $count = $sorted->slice($i)->filter(fn ($e) => $e['at']->lte($end))->count();

                if ($count > $peak) {
                    $peak   = $count;
                    $peakAt = $event['at'];
                }
            }

            if ($peak < $limit) continue;

            $alerts[] = new Alert(
                module: $this->key(),
                type: 'rafaga_acciones_sensibles',
                severity: Severity::ALTA,
                title: "{$email} hizo {$peak} acciones sensibles en {$window} minutos",
                recommendation: 'Un volumen asi casi nunca es trabajo manual: o es una importacion masiva que alguien lanzo sin avisar, o es una cuenta tomada vaciando el catalogo. Identifica la herramienta usada y, si no hay explicacion, cierra la sesion de ese usuario y cambia su contrasena.',
                evidence: [
                    'usuario'         => $email,
                    'acciones'        => $peak,
                    'ventana_minutos' => $window,
                    'desde'           => $peakAt?->format('Y-m-d H:i'),
                    'tipos'           => $group->pluck('action')->countBy()->all(),
                ],
                tenant: $context->tenant,
                dedupeKey: "{$context->scopeKey()}:schedule:burst:{$email}:" . ($peakAt?->format('YmdH') ?? ''),
            );
        }

        return $alerts;
    }

    // ── Reporte de jornada ───────────────────────────────────────────────────

    private function dailyReport(Collection $events, WorkSchedule $schedule, ScanContext $context): array
    {
        $day   = $context->now->format('Y-m-d');
        $today = $events->filter(fn ($e) => $e['at']->format('Y-m-d') === $day);

        if ($today->isEmpty()) {
            return [];
        }

        $rows = [];

        foreach ($today->groupBy('email') as $email => $group) {
            $sorted  = $group->sortBy(fn ($e) => $e['at']->getTimestamp())->values();
            $outside = $group->filter(fn ($e) => $schedule->reasonOutside($e['at'], $e['email']) !== null)->count();

            $rows[] = [
                'usuario'         => $email,
                'primer_evento'   => $sorted->first()['at']->format('H:i'),
                'ultimo_evento'   => $sorted->last()['at']->format('H:i'),
                'total'           => $group->count(),
                'fuera_de_horario'=> $outside,
                'sensibles'       => $group->where('sensitive', true)->count(),
            ];
        }

        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [new Alert(
            module: $this->key(),
            type: 'reporte_de_jornada',
            severity: Severity::BAJA,
            title: sprintf('Jornada del %s: %d usuario(s) con actividad', $day, count($rows)),
            recommendation: 'Resumen informativo, no requiere accion. Sirve para contrastar la actividad real con los horarios declarados. Al estar en severidad BAJA no dispara notificaciones: se consulta en el reporte HTML o en el historial JSONL.',
            evidence: ['dia' => $day, 'usuarios' => $rows],
            tenant: $context->tenant,
            dedupeKey: "{$context->scopeKey()}:schedule:report:{$day}",
        )];
    }

    // ── Lectura de las tres fuentes ──────────────────────────────────────────

    /**
     * @return Collection<int,array{email:string,at:CarbonImmutable,action:string,sensitive:bool,source:string}>
     */
    private function collectEvents(ScanContext $context, CarbonImmutable $since): Collection
    {
        $events = collect();

        foreach (['loginEvents', 'priceChanges', 'auditLogs'] as $source) {
            try {
                $events = $events->merge($this->{$source}($context, $since));
            } catch (\Throwable $e) {
                // Una fuente ausente (tabla que no existe en ese tenant) no
                // debe dejar el modulo sin las otras dos.
                continue;
            }
        }

        return $events->filter(fn ($e) => $e['email'] !== '')->values();
    }

    private function loginEvents(ScanContext $context, CarbonImmutable $since): Collection
    {
        return collect($this->db->table('login_events')
            ->where('created_at', '>=', $since)
            ->whereIn('result', ['success', 'logout'])
            ->limit(20000)
            ->get(['email', 'result', 'created_at']))
            ->map(fn ($row) => [
                'email'     => strtolower((string) $row->email),
                'at'        => CarbonImmutable::parse($row->created_at),
                'action'    => $row->result === 'logout' ? 'cierre de sesion' : 'ingreso',
                'sensitive' => false,
                'source'    => 'login_events',
            ]);
    }

    private function priceChanges(ScanContext $context, CarbonImmutable $since): Collection
    {
        return collect($this->db->table('item_price_history')
            ->where('created_at', '>=', $since)
            ->whereNotNull('changed_by')
            ->limit(20000)
            ->get(['changed_by', 'change_type', 'created_at']))
            ->map(fn ($row) => [
                'email'     => strtolower((string) $row->changed_by),
                'at'        => CarbonImmutable::parse($row->created_at),
                'action'    => 'cambio de ' . ($row->change_type ?? 'precio'),
                'sensitive' => true,
                'source'    => 'item_price_history',
            ]);
    }

    private function auditLogs(ScanContext $context, CarbonImmutable $since): Collection
    {
        $rows = $this->db->table('audit_logs as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->where('a.created_at', '>=', $since)
            ->limit(20000)
            ->get(['u.email', 'a.action', 'a.module', 'a.created_at']);

        $map = (array) $context->config->get('work_schedule.sensitive_audit', []);

        return collect($rows)->map(function ($row) use ($map) {
            $signature = "{$row->module}:{$row->action}";

            return [
                'email'     => strtolower((string) $row->email),
                'at'        => CarbonImmutable::parse($row->created_at),
                'action'    => $map[$signature] ?? $signature,
                'sensitive' => isset($map[$signature]) || in_array($row->action, ['delete', 'export'], true),
                'source'    => 'audit_logs',
            ];
        });
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
