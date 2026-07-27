<?php

namespace App\Services\Tenant\Raffles;

use App\Services\Tenant\Raffles\Sources\AllCustomersSource;
use App\Services\Tenant\Raffles\Sources\CustomListSource;
use App\Services\Tenant\Raffles\Sources\EcommerceSource;
use App\Services\Tenant\Raffles\Sources\FrequentCustomersSource;
use App\Services\Tenant\Raffles\Sources\MarketplaceSource;
use App\Services\Tenant\Raffles\Sources\OrdersManagementSource;
use App\Services\Tenant\Raffles\Sources\PosSource;
use App\Services\Tenant\Raffles\Sources\ShipmentsSource;
use Illuminate\Support\Collection;

/**
 * Catálogo de orígenes de participantes.
 *
 * Para sumar un módulo nuevo al sorteo (reservas, servicios, suscripciones…)
 * basta con crear su clase ParticipantSource y agregarla a SOURCES. Ni el
 * controlador, ni el motor de elegibilidad, ni las vistas necesitan cambios:
 * el formulario se pinta desde `filters()` y la vista previa desde `resolve()`.
 */
class ParticipantSourceRegistry
{
    /** Orden = orden en el selector del formulario. */
    private const SOURCES = [
        OrdersManagementSource::class,
        ShipmentsSource::class,
        EcommerceSource::class,
        PosSource::class,
        MarketplaceSource::class,
        FrequentCustomersSource::class,
        AllCustomersSource::class,
        CustomListSource::class,
    ];

    /** @var array<string, ParticipantSource> */
    private array $instances = [];

    public function __construct()
    {
        foreach (self::SOURCES as $class) {
            $source = new $class();
            $this->instances[$source->key()] = $source;
        }
    }

    /** @return Collection<string, ParticipantSource> todos, disponibles o no */
    public function all(): Collection
    {
        return collect($this->instances);
    }

    /** @return Collection<string, ParticipantSource> solo los usables en este tenant */
    public function available(): Collection
    {
        return $this->all()->filter(fn (ParticipantSource $s) => $s->available());
    }

    public function has(string $key): bool
    {
        return isset($this->instances[$key]);
    }

    /** Origen por clave; null si no existe. */
    public function get(?string $key): ?ParticipantSource
    {
        return $key !== null ? ($this->instances[$key] ?? null) : null;
    }

    /**
     * Origen por clave con respaldo al primero disponible, para que un sorteo
     * guardado con un origen que luego se desinstaló siga abriéndose.
     */
    public function resolveOrDefault(?string $key): ParticipantSource
    {
        return $this->get($key) ?? $this->available()->first() ?? new OrdersManagementSource();
    }

    public function keys(): array
    {
        return array_keys($this->instances);
    }

    /** Etiqueta legible de un origen (para vistas y CSV). */
    public function label(?string $key): string
    {
        return $this->get($key)?->label() ?? ($key ?: '—');
    }
}
