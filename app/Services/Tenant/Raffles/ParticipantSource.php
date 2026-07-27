<?php

namespace App\Services\Tenant\Raffles;

use App\Models\Tenant\Person;
use App\Models\Tenant\Raffle;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Origen de participantes de un sorteo.
 *
 * Cada módulo del ERP que pueda aportar clientes (pedidos, envíos, tienda
 * virtual, POS, marketplace…) implementa esta clase. El motor del sorteo no
 * sabe nada de cada módulo: pide `filters()` para pintar el formulario y
 * `resolve()` para obtener candidatos. Añadir un origen nuevo (reservas,
 * servicios, etc.) = crear una clase y registrarla en
 * ParticipantSourceRegistry — sin tocar la lógica del sorteo.
 *
 * Contrato de `resolve()`: devuelve UNA fila por cliente YA AGRUPADA dentro
 * del origen, con estas claves:
 *   person_id, full_name, document, email, phone, records, amount, last_at
 * `records` = cuántos registros del origen aportó ese cliente (sirve para el
 * conteo de "duplicados eliminados" de la vista previa).
 *
 * La deduplicación entre orígenes y las validaciones finales las hace
 * RaffleEligibilityService, no las fuentes.
 */
abstract class ParticipantSource
{
    /** Identificador estable que se guarda en `raffles.source`. */
    abstract public function key(): string;

    /** Nombre visible en el selector de origen. */
    abstract public function label(): string;

    /** Frase corta que explica de dónde saca los clientes. */
    public function description(): string
    {
        return '';
    }

    /** Emoji del selector (el panel no carga icon fonts extra). */
    public function icon(): string
    {
        return '📋';
    }

    /**
     * Esquema de filtros para el formulario. Cada entrada:
     *   ['key' => 'paid', 'type' => 'boolean', 'label' => '…', 'default' => true]
     * Tipos soportados por la vista: boolean, date, number, text, textarea,
     * select (con 'options' => [valor => etiqueta]), multiselect, items.
     */
    abstract public function filters(): array;

    /**
     * ¿Está disponible este origen en el tenant actual? Un tenant sin el
     * módulo de Envíos no tiene la tabla `shipping_requests`, y el origen
     * debe desaparecer del selector en vez de reventar.
     */
    public function available(): bool
    {
        return true;
    }

    /** Motivo por el que no está disponible (se muestra deshabilitado). */
    public function unavailableReason(): string
    {
        return 'Este módulo no está instalado en tu tienda.';
    }

    /** @return Collection<int, array> candidatos ya agrupados por cliente */
    abstract public function resolve(Raffle $raffle): Collection;

    // ── Helpers para las implementaciones ──────────────────────────────────

    /** Valor de un filtro guardado en el sorteo, con default del esquema. */
    protected function filter(Raffle $raffle, string $key, $default = null)
    {
        $stored = $raffle->source_filters[$key] ?? null;

        if ($stored !== null && $stored !== '' && $stored !== []) {
            return $stored;
        }

        if ($default !== null) {
            return $default;
        }

        foreach ($this->filters() as $f) {
            if (($f['key'] ?? null) === $key) {
                return $f['default'] ?? null;
            }
        }

        return null;
    }

    protected function boolFilter(Raffle $raffle, string $key, bool $default = false): bool
    {
        $v = $raffle->source_filters[$key] ?? $default;

        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }

    protected function arrayFilter(Raffle $raffle, string $key): array
    {
        return array_values(array_filter((array) ($raffle->source_filters[$key] ?? [])));
    }

    /** Aplica un rango de fechas si los filtros date_from / date_to están puestos. */
    protected function applyDates($query, Raffle $raffle, string $column, string $fromKey = 'date_from', string $toKey = 'date_to'): void
    {
        if ($from = $this->filter($raffle, $fromKey)) {
            $query->whereDate($column, '>=', $from);
        }
        if ($to = $this->filter($raffle, $toKey)) {
            $query->whereDate($column, '<=', $to);
        }
    }

    protected function tableExists(string $table): bool
    {
        try {
            return Schema::connection('tenant')->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Agrupa una consulta por la columna de cliente y resuelve nombre,
     * documento, email y teléfono desde `persons`.
     */
    protected function groupByPerson(EloquentBuilder $query, string $personColumn, string $dateColumn, string $totalColumn = 'total'): Collection
    {
        $rows = $query->selectRaw(
                    "{$personColumn} as pid, count(*) as records, "
                  . "coalesce(sum({$totalColumn}), 0) as amount, max({$dateColumn}) as last_at"
                )
                ->groupBy($personColumn)
                ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $people = Person::whereIn('id', $rows->pluck('pid')->filter()->all())
                        ->get(['id', 'name', 'number', 'email', 'telephone'])
                        ->keyBy('id');

        return $rows->map(function ($row) use ($people) {
            $person = $people->get($row->pid);

            return [
                'person_id' => (int) $row->pid,
                'full_name' => $person->name ?? 'Cliente',
                'document'  => $person->number ?? null,
                'email'     => $person->email ?? null,
                'phone'     => $person->telephone ?? null,
                'records'   => (int) $row->records,
                'amount'    => (float) $row->amount,
                'last_at'   => $row->last_at ? substr((string) $row->last_at, 0, 10) : null,
            ];
        })->filter(fn ($r) => $r['person_id'] > 0)->values();
    }

    /** Fila normalizada para orígenes que NO se apoyan en `persons`. */
    protected function row(?int $personId, ?string $name, ?string $document, ?string $email, ?string $phone, int $records = 1, float $amount = 0.0, ?string $lastAt = null): array
    {
        return [
            'person_id' => $personId ?: 0,
            'full_name' => $name ?: 'Cliente',
            'document'  => $document ?: null,
            'email'     => $email ?: null,
            'phone'     => $phone ?: null,
            'records'   => $records,
            'amount'    => $amount,
            'last_at'   => $lastAt,
        ];
    }
}
