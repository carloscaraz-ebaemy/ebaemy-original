<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Raffle;
use App\Models\Tenant\RaffleParticipant;
use App\Services\Tenant\Raffles\ParticipantSource;
use App\Services\Tenant\Raffles\ParticipantSourceRegistry;
use Illuminate\Support\Collection;

/**
 * Motor de elegibilidad del sorteo.
 *
 * No sabe de qué módulo salen los clientes: le pide los candidatos al
 * ParticipantSource configurado (Gestión de Pedidos, Envíos, Tienda Virtual,
 * POS, Marketplace, Clientes frecuentes, Todos los clientes, Lista
 * personalizada…) y se encarga solo de lo que es común a todos:
 *
 *   1. Deduplicar (documento → email → teléfono → person_id).
 *   2. Acumular pedidos y monto de cada cliente.
 *   3. Aplicar las reglas globales del sorteo (monto mínimo acumulado y
 *      exigir un dato de contacto).
 *   4. Producir las estadísticas de la vista previa.
 *
 * Añadir un origen nuevo NO exige tocar esta clase.
 */
class RaffleEligibilityService
{
    private ParticipantSourceRegistry $registry;

    public function __construct(?ParticipantSourceRegistry $registry = null)
    {
        $this->registry = $registry ?: new ParticipantSourceRegistry();
    }

    public function registry(): ParticipantSourceRegistry
    {
        return $this->registry;
    }

    public function sourceFor(Raffle $raffle): ParticipantSource
    {
        return $this->registry->resolveOrDefault($raffle->source);
    }

    /**
     * Analiza el universo del sorteo.
     *
     * @return array{rows: Collection, stats: array}
     *   stats: found, unique, duplicates, rejected, rejected_no_contact,
     *          rejected_min_amount, source, source_label
     */
    public function analyze(Raffle $raffle): array
    {
        $source     = $this->sourceFor($raffle);
        $candidates = $source->resolve($raffle);

        // "Encontrados" = registros del origen que cumplen los filtros (un
        // cliente con 3 pedidos aporta 3). La diferencia contra los únicos es
        // exactamente lo que el panel reporta como duplicados eliminados.
        $found = (int) $candidates->sum(fn ($r) => max(1, (int) ($r['records'] ?? 1)));

        $merged = [];

        foreach ($candidates as $row) {
            $key = RaffleParticipant::dedupeKeyFor(
                $row['document'] ?? null,
                $row['email'] ?? null,
                $row['phone'] ?? null,
                $row['person_id'] ?? null
            );

            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'person_id'        => (int) ($row['person_id'] ?? 0),
                    'full_name'        => $row['full_name'] ?: 'Cliente',
                    'document'         => $row['document'] ?: null,
                    'email'            => $row['email'] ?: null,
                    'phone'            => $row['phone'] ?: null,
                    'dedupe_key'       => $key,
                    'orders_count'     => max(1, (int) ($row['records'] ?? 1)),
                    'total_amount'     => (float) ($row['amount'] ?? 0),
                    'last_purchase_at' => $row['last_at'] ?? null,
                ];
                continue;
            }

            $merged[$key]['orders_count'] += max(1, (int) ($row['records'] ?? 1));
            $merged[$key]['total_amount'] += (float) ($row['amount'] ?? 0);

            if (!empty($row['last_at']) && (empty($merged[$key]['last_purchase_at'])
                || $row['last_at'] > $merged[$key]['last_purchase_at'])) {
                $merged[$key]['last_purchase_at'] = $row['last_at'];
            }

            // Completar contacto faltante con lo que traiga el otro registro.
            foreach (['email', 'phone', 'document'] as $field) {
                if (empty($merged[$key][$field]) && !empty($row[$field])) {
                    $merged[$key][$field] = $row[$field];
                }
            }
            if (empty($merged[$key]['person_id']) && !empty($row['person_id'])) {
                $merged[$key]['person_id'] = (int) $row['person_id'];
            }
        }

        $unique = collect(array_values($merged));

        // ── Reglas globales del sorteo ──────────────────────────────────
        $minAmount = (float) ($raffle->min_amount ?? 0);

        $rejectedNoContact = 0;
        $rejectedMinAmount = 0;

        $eligible = $unique->filter(function ($row) use ($minAmount, &$rejectedNoContact, &$rejectedMinAmount) {
            // Sin ningún dato de contacto no se le puede hacer llegar el enlace.
            if (empty($row['phone']) && empty($row['email'])) {
                $rejectedNoContact++;
                return false;
            }

            if ($minAmount > 0 && $row['total_amount'] < $minAmount) {
                $rejectedMinAmount++;
                return false;
            }

            return true;
        })->sortByDesc('total_amount')->values();

        return [
            'rows'  => $eligible,
            'stats' => [
                'found'               => $found,
                'unique'              => $unique->count(),
                'duplicates'          => max(0, $found - $unique->count()),
                'eligible'            => $eligible->count(),
                'rejected'            => $rejectedNoContact + $rejectedMinAmount,
                'rejected_no_contact' => $rejectedNoContact,
                'rejected_min_amount' => $rejectedMinAmount,
                'source'              => $source->key(),
                'source_label'        => $source->label(),
            ],
        ];
    }

    /** Solo las filas elegibles. */
    public function eligible(Raffle $raffle): Collection
    {
        return $this->analyze($raffle)['rows'];
    }

    public function countEligible(Raffle $raffle): int
    {
        return $this->analyze($raffle)['stats']['eligible'];
    }

    /**
     * Confirma los participantes: crea un RaffleParticipant por cliente
     * elegible que aún no exista. Es idempotente — los ya creados conservan
     * su token y su aceptación, así que se puede re-ejecutar para incorporar
     * clientes nuevos sin resetear la campaña.
     *
     * @return array{created:int, existing:int, total:int, stats:array}
     */
    public function syncParticipants(Raffle $raffle): array
    {
        $analysis = $this->analyze($raffle);
        $existing = $raffle->participants()->pluck('dedupe_key')->flip();

        $created = 0;

        foreach ($analysis['rows'] as $row) {
            if ($existing->has($row['dedupe_key'])) {
                continue;
            }

            RaffleParticipant::create([
                'raffle_id'        => $raffle->id,
                'person_id'        => $row['person_id'] ?: null,
                'full_name'        => mb_substr($row['full_name'] ?: 'Cliente', 0, 200),
                'document'         => $row['document'] ?: null,
                'email'            => $row['email'] ?: null,
                'phone'            => $row['phone'] ?: null,
                'dedupe_key'       => $row['dedupe_key'],
                'token'            => RaffleParticipant::makeToken(),
                'status'           => RaffleParticipant::STATUS_INVITED,
                'orders_count'     => $row['orders_count'],
                'total_amount'     => $row['total_amount'],
                'last_purchase_at' => $row['last_purchase_at'],
            ]);

            $created++;
        }

        if ($created > 0 || !$raffle->participants_confirmed_at) {
            $raffle->forceFill(['participants_confirmed_at' => now()])->save();
        }

        return [
            'created'  => $created,
            'existing' => $existing->count(),
            'total'    => $raffle->participants()->count(),
            'stats'    => $analysis['stats'],
        ];
    }
}
