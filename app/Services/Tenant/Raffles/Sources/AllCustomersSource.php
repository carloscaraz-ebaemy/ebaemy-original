<?php

namespace App\Services\Tenant\Raffles\Sources;

use App\Models\Tenant\Person;
use App\Models\Tenant\Raffle;
use App\Services\Tenant\Raffles\ParticipantSource;
use Illuminate\Support\Collection;

/**
 * Todos los clientes: la agenda completa (`persons` de tipo cliente), sin
 * exigir que hayan comprado. Útil para campañas de captación o para sortear
 * entre toda la base registrada.
 */
class AllCustomersSource extends ParticipantSource
{
    public function key(): string
    {
        return 'all_customers';
    }

    public function label(): string
    {
        return 'Todos los clientes';
    }

    public function description(): string
    {
        return 'Toda la agenda de clientes registrada, hayan comprado o no.';
    }

    public function icon(): string
    {
        return '👥';
    }

    public function filters(): array
    {
        return [
            [
                'key' => 'with_phone', 'type' => 'boolean', 'default' => true,
                'label' => 'Solo clientes con teléfono',
                'help'  => 'Necesario para enviarles la invitación por WhatsApp.',
            ],
            ['key' => 'with_email', 'type' => 'boolean', 'label' => 'Solo clientes con correo'],
            ['key' => 'date_from', 'type' => 'date', 'label' => 'Registrados desde'],
            ['key' => 'date_to',   'type' => 'date', 'label' => 'Registrados hasta'],
            [
                'key' => 'only_enabled', 'type' => 'boolean', 'default' => true,
                'label' => 'Solo clientes activos',
            ],
        ];
    }

    public function resolve(Raffle $raffle): Collection
    {
        $query = Person::query()->where('type', 'customers');

        $this->applyDates($query, $raffle, 'created_at');

        if ($this->boolFilter($raffle, 'only_enabled', true)) {
            $query->where(fn ($q) => $q->where('enabled', 1)->orWhereNull('enabled'));
        }

        if ($this->boolFilter($raffle, 'with_phone', true)) {
            $query->whereNotNull('telephone')->where('telephone', '!=', '');
        }

        if ($this->boolFilter($raffle, 'with_email')) {
            $query->whereNotNull('email')->where('email', '!=', '');
        }

        return $query->get(['id', 'name', 'number', 'email', 'telephone', 'created_at'])
                     ->map(fn ($p) => $this->row(
                         $p->id, $p->name, $p->number, $p->email, $p->telephone,
                         1, 0.0, optional($p->created_at)->format('Y-m-d')
                     ))
                     ->values();
    }
}
