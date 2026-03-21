<?php

namespace App\Enums;

/**
 * LogisticStatusEnum — Estados logísticos de una Nota de Venta.
 *
 * Flujo provincia (requires_warehouse_dispatch = TRUE):
 *   PENDIENTE → PREPARANDO → LISTO_DESPACHO → DESPACHADO
 *
 * Flujo tienda (requires_warehouse_dispatch = FALSE):
 *   ENTREGA_INMEDIATA  (estado final desde la creación)
 */
enum LogisticStatusEnum: string
{
    case PENDIENTE         = 'PENDIENTE';
    case PREPARANDO        = 'PREPARANDO';
    case LISTO_DESPACHO    = 'LISTO_DESPACHO';
    case DESPACHADO        = 'DESPACHADO';
    case RECOGIDO          = 'RECOGIDO';       // Cliente vino a recoger (PICKUP)
    case ENTREGA_INMEDIATA = 'ENTREGA_INMEDIATA';
    case ANULADO           = 'ANULADO';        // Despacho anulado — stock revertido

    // ─── Etiqueta legible ─────────────────────────────────────────────────────

    public function label(): string
    {
        return match($this) {
            self::PENDIENTE         => 'Pendiente',
            self::PREPARANDO        => 'Preparando',
            self::LISTO_DESPACHO    => 'Listo para Despacho',
            self::DESPACHADO        => 'Despachado',
            self::RECOGIDO          => 'Recogido por Cliente',
            self::ENTREGA_INMEDIATA => 'Entrega Inmediata',
            self::ANULADO           => 'Anulado',
        };
    }

    // ─── Color de badge Bootstrap ─────────────────────────────────────────────

    public function badgeColor(): string
    {
        return match($this) {
            self::PENDIENTE         => 'warning',
            self::PREPARANDO        => 'primary',
            self::LISTO_DESPACHO    => 'info',
            self::DESPACHADO        => 'success',
            self::RECOGIDO          => 'success',
            self::ENTREGA_INMEDIATA => 'secondary',
            self::ANULADO           => 'danger',
        };
    }

    // ─── Transiciones permitidas ──────────────────────────────────────────────

    /** @return self[] */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::PENDIENTE         => [self::PREPARANDO],
            self::PREPARANDO        => [self::LISTO_DESPACHO],
            self::LISTO_DESPACHO    => [self::DESPACHADO, self::RECOGIDO],
            self::DESPACHADO        => [self::ANULADO],
            self::RECOGIDO          => [],
            self::ENTREGA_INMEDIATA => [],
            self::ANULADO           => [],
        };
    }

    public function canTransitionTo(self $new): bool
    {
        return in_array($new, $this->allowedTransitions());
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Estado que ya no puede modificarse */
    public function isFinal(): bool
    {
        return in_array($this, [self::DESPACHADO, self::RECOGIDO, self::ENTREGA_INMEDIATA, self::ANULADO]);
    }

    /** Estados visibles en la cola del almacén */
    public static function queueStatuses(): array
    {
        return [self::PENDIENTE, self::PREPARANDO, self::LISTO_DESPACHO];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
