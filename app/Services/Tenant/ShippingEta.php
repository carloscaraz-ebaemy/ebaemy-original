<?php

namespace App\Services\Tenant;

use App\Models\Tenant\ShippingSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Fechas estimadas que ve el cliente en el formulario de envío.
 *
 * La llegada depende de cuándo SALE el paquete de la tienda: primero se
 * calcula la salida (hoy + días de preparación de la política de envío, o
 * mañana si ya pasó la hora de corte) y la llegada se cuenta desde ahí.
 *
 * Días hábiles: lunes a sábado sin feriados nacionales. Las agencias y los
 * motorizados trabajan los sábados; el domingo y los feriados no cuentan.
 * Es una estimación para fijar expectativas, no un compromiso.
 */
class ShippingEta
{
    public function __construct(private ?CarbonInterface $now = null) {}

    /**
     * @return array{agencia: array, domicilio: array, tienda: array}
     */
    public function forForm(?ShippingSetting $st = null): array
    {
        $st  = $st ?? ShippingSetting::current();
        $now = $this->now ? Carbon::instance($this->now) : Carbon::now('America/Lima');

        // Pasada la hora de corte, hoy ya no se prepara nada.
        $start = $now->copy()->startOfDay();
        $cutoff = $st->cutoff_hhmm;
        if ($cutoff && $now->format('H:i') >= $cutoff) {
            $start->addDay();
        }
        $start = $this->toBusinessDay($start);

        $handling = max(0, (int) ($st->province_handling_days ?? 1));
        $tMin = max(0, (int) ($st->province_transit_min ?? 2));
        $tMax = max($tMin, (int) ($st->province_transit_max ?? 5));
        $leave = $this->addBusinessDays($start, $handling);

        $lMin = max(0, (int) ($st->lima_days_min ?? 0));
        $lMax = max($lMin, (int) ($st->lima_days_max ?? 1));

        $ready = max(1, (int) ($st->pickup_ready_hours ?? 24));
        $readyAt = $this->toBusinessDay($now->copy()->addHours($ready)->startOfDay());

        return [
            'agencia' => [
                'leave'      => $this->fmt($leave),
                'leave_rel'  => $this->relative($leave, $now),
                'arrive_min' => $this->fmt($this->addBusinessDays($leave, $tMin)),
                'arrive_max' => $this->fmt($this->addBusinessDays($leave, $tMax)),
                'range'      => $this->range($this->addBusinessDays($leave, $tMin), $this->addBusinessDays($leave, $tMax)),
                // El viaje lo define la agencia: aquí solo se promete la salida.
                'short'      => 'Sale de la tienda ' . $this->relative($leave, $now),
            ],
            'domicilio' => [
                'range' => $this->range($this->addBusinessDays($start, $lMin), $this->addBusinessDays($start, $lMax)),
                'short' => 'Llega ' . $this->rangeShort($this->addBusinessDays($start, $lMin), $this->addBusinessDays($start, $lMax)),
            ],
            'tienda' => [
                'ready' => $this->fmt($readyAt),
                'short' => 'Listo ' . $this->relative($readyAt, $now),
            ],
            'cutoff' => $cutoff,
        ];
    }

    /**
     * Llegada con el tiempo que da Shalom para la ruta (POST /account/quote),
     * contado desde la salida de la tienda. Las horas se pasan a días hábiles
     * redondeando hacia arriba: 48 h = 2 días, 30 h = 2 días.
     *
     * @return array{date:string, short:string, days:int}
     */
    public function shalomArrival(int $hours, ?ShippingSetting $st = null): array
    {
        $st  = $st ?? ShippingSetting::current();
        $now = $this->now ? Carbon::instance($this->now) : Carbon::now('America/Lima');
        $start = $now->copy()->startOfDay();
        if ($st->cutoff_hhmm && $now->format('H:i') >= $st->cutoff_hhmm) {
            $start->addDay();
        }
        $leave = $this->addBusinessDays($this->toBusinessDay($start), max(0, (int) ($st->province_handling_days ?? 1)));
        $days = max(1, (int) ceil($hours / 24));
        $arrive = $this->addBusinessDays($leave, $days);

        return ['date' => $this->fmt($arrive), 'short' => $this->dayAbbr($arrive) . ' ' . $arrive->day, 'days' => $days];
    }

    private function addBusinessDays(CarbonInterface $d, int $n): Carbon
    {
        $d = $this->toBusinessDay(Carbon::instance($d)->copy());
        while ($n > 0) {
            $d->addDay();
            if ($this->isBusinessDay($d)) {
                $n--;
            }
        }

        return $d;
    }

    private function toBusinessDay(Carbon $d): Carbon
    {
        while (!$this->isBusinessDay($d)) {
            $d->addDay();
        }

        return $d;
    }

    private function isBusinessDay(CarbonInterface $d): bool
    {
        return !$d->isSunday() && !in_array($d->format('Y-m-d'), $this->holidays((int) $d->year), true);
    }

    /** Feriados nacionales del Perú: los de fecha fija más Jueves y Viernes Santo. */
    private function holidays(int $y): array
    {
        static $cache = [];
        if (isset($cache[$y])) {
            return $cache[$y];
        }

        $fixed = ['01-01', '05-01', '06-07', '06-29', '07-23', '07-28', '07-29', '08-06', '08-30', '10-08', '11-01', '12-08', '12-09', '12-25'];
        $days = array_map(fn ($md) => "{$y}-{$md}", $fixed);

        // Domingo de Pascua (algoritmo anónimo gregoriano).
        $a = $y % 19; $b = intdiv($y, 100); $c = $y % 100; $d = intdiv($b, 4); $e = $b % 4;
        $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3); $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;
        $easter = Carbon::create($y, $month, $day);
        $days[] = $easter->copy()->subDays(3)->format('Y-m-d');
        $days[] = $easter->copy()->subDays(2)->format('Y-m-d');

        return $cache[$y] = $days;
    }

    private function fmt(CarbonInterface $d): string
    {
        return $this->dayName($d) . ' ' . $d->day . ' de ' . $this->monthName($d);
    }

    /** «entre el vie 1 y el mar 5 de nov», o un solo día si coinciden. */
    private function range(CarbonInterface $a, CarbonInterface $b): string
    {
        if ($a->isSameDay($b)) {
            return 'el ' . $this->fmt($a);
        }
        $first = $this->dayName($a) . ' ' . $a->day . ($a->month !== $b->month ? ' de ' . $this->monthName($a) : '');

        return 'entre el ' . $first . ' y el ' . $this->fmt($b);
    }

    private function rangeShort(CarbonInterface $a, CarbonInterface $b): string
    {
        $s = fn (CarbonInterface $d) => $this->dayAbbr($d) . ' ' . $d->day;

        return $a->isSameDay($b) ? $s($a) : ($s($a) . ' a ' . $s($b));
    }

    private function relative(CarbonInterface $d, CarbonInterface $now): string
    {
        $diff = $now->copy()->startOfDay()->diffInDays(Carbon::instance($d)->startOfDay(), false);

        return match (true) {
            $diff <= 0 => 'hoy',
            $diff === 1 => 'mañana',
            default => 'el ' . $this->dayName($d) . ' ' . $d->day,
        };
    }

    private function dayName(CarbonInterface $d): string
    {
        return ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'][$d->dayOfWeek];
    }

    private function dayAbbr(CarbonInterface $d): string
    {
        return ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'][$d->dayOfWeek];
    }

    private function monthName(CarbonInterface $d): string
    {
        return ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'][$d->month - 1];
    }
}
