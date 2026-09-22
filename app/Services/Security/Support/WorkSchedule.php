<?php

namespace App\Services\Security\Support;

use Carbon\CarbonImmutable;

/**
 * Jornada laboral configurable: dias, horas, tolerancia, feriados de Peru y
 * turnos especiales por usuario.
 *
 * Soporta turnos que cruzan la medianoche (por ejemplo 22:00–06:00): en ese
 * caso la hora de fin se entiende como del dia siguiente.
 */
final class WorkSchedule
{
    private const DAYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public function __construct(
        private readonly array $schedule,
        private readonly array $holidays = [],
        private readonly int $toleranceMinutes = 15,
        private readonly array $userExceptions = [],
    ) {}

    public static function fromConfig(AgentConfig $config): self
    {
        return new self(
            (array) $config->get('work_schedule.schedule', []),
            (array) $config->get('work_schedule.holidays', []),
            (int) $config->get('work_schedule.tolerance_minutes', 15),
            (array) $config->get('work_schedule.user_exceptions', []),
        );
    }

    /** ¿Ese momento cae dentro de la jornada de ese usuario? */
    public function covers(CarbonImmutable $moment, ?string $email = null): bool
    {
        return $this->reasonOutside($moment, $email) === null;
    }

    /**
     * Por que ese momento queda FUERA de horario, o null si esta dentro.
     */
    public function reasonOutside(CarbonImmutable $moment, ?string $email = null): ?string
    {
        $schedule = $this->scheduleFor($email);

        if ($this->isHoliday($moment)) {
            return 'feriado';
        }

        $day   = self::DAYS[(int) $moment->format('w')];
        $hours = $schedule[$day] ?? null;

        // Un turno que empezo ayer y cruza la medianoche cubre esta madrugada.
        if ($this->coveredByPreviousDay($moment, $schedule)) {
            return null;
        }

        if ($hours === null || $hours === []) {
            return 'dia no laborable';
        }

        [$start, $end] = $this->boundsFor($moment, $hours);

        if ($moment->lt($start)) return 'antes del inicio de la jornada';
        if ($moment->gt($end))   return 'despues del cierre de la jornada';

        return null;
    }

    public function isHoliday(CarbonImmutable $moment): bool
    {
        return in_array($moment->format('Y-m-d'), $this->holidays, true);
    }

    /** Horario efectivo: el del usuario si tiene excepcion, el general si no. */
    public function scheduleFor(?string $email): array
    {
        if ($email) {
            foreach ($this->userExceptions as $candidate => $custom) {
                if (strtolower((string) $candidate) === strtolower($email) && is_array($custom)) {
                    // La excepcion solo pisa los dias que declara.
                    return array_merge($this->schedule, $custom);
                }
            }
        }

        return $this->schedule;
    }

    /** @return array{0:CarbonImmutable,1:CarbonImmutable} inicio y fin con tolerancia */
    private function boundsFor(CarbonImmutable $moment, array $hours): array
    {
        [$from, $to] = $hours;

        $start = $this->at($moment, $from)->subMinutes($this->toleranceMinutes);
        $end   = $this->at($moment, $to)->addMinutes($this->toleranceMinutes);

        // Turno nocturno: el cierre es del dia siguiente.
        if ($end->lte($start)) {
            $end = $end->addDay();
        }

        return [$start, $end];
    }

    private function coveredByPreviousDay(CarbonImmutable $moment, array $schedule): bool
    {
        $yesterday = $moment->subDay();
        $day       = self::DAYS[(int) $yesterday->format('w')];
        $hours     = $schedule[$day] ?? null;

        if ($hours === null || $hours === []) return false;

        [$from, $to] = $hours;

        // Solo interesa si el turno de ayer cruzaba la medianoche.
        if (strcmp($to, $from) >= 0) return false;

        $end = $this->at($yesterday, $to)->addDay()->addMinutes($this->toleranceMinutes);

        return $moment->lte($end);
    }

    private function at(CarbonImmutable $day, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        return $day->setTime((int) $hour, (int) $minute, 0);
    }
}
