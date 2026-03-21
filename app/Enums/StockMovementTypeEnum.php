<?php

namespace App\Enums;

enum StockMovementTypeEnum: string
{
    // --- Ventas ---
    case SALE_STORE          = 'sale_store';          // POS tienda: -physical
    case SALE_STORE_RETURN   = 'sale_store_return';   // Devolución tienda: +physical

    // --- Provincia (committed) ---
    case PROVINCE_COMMIT     = 'province_commit';     // Pedido provincia: +committed
    case PROVINCE_DISPATCH   = 'province_dispatch';   // Despacho: -committed, -physical
    case PROVINCE_CANCEL     = 'province_cancel';     // Cancelación: -committed (devuelve)
    case DISPATCH_ANNUL      = 'dispatch_annul';      // Anulación despacho: +committed, +physical

    // --- Ecommerce ---
    case ECOMMERCE_RESERVE   = 'ecommerce_reserve';   // Checkout: +committed (reserva)
    case ECOMMERCE_CANCEL    = 'ecommerce_cancel';    // Abandono/timeout: -committed
    case ECOMMERCE_DISPATCH  = 'ecommerce_dispatch';  // Despacho ecom: -committed, -physical

    // --- Compras ---
    case PURCHASE_ENTRY      = 'purchase_entry';      // Recepción compra: +physical

    // --- Devoluciones ---
    case RETURN_RESTOCK      = 'return_restock';      // Devolución buena condición: +physical
    case RETURN_DAMAGED      = 'return_damaged';      // Devolución dañada: sin movimiento en stock

    // --- Internos ---
    case ADJUSTMENT_IN       = 'adjustment_in';       // Ajuste positivo: +physical
    case ADJUSTMENT_OUT      = 'adjustment_out';      // Ajuste negativo: -physical
    case TRANSFER_OUT        = 'transfer_out';        // Transferencia salida: -physical
    case TRANSFER_IN         = 'transfer_in';         // Transferencia entrada: +physical

    public function label(): string
    {
        return match($this) {
            self::SALE_STORE         => 'Venta Tienda',
            self::SALE_STORE_RETURN  => 'Devolución Tienda',
            self::PROVINCE_COMMIT    => 'Reserva Provincia',
            self::PROVINCE_DISPATCH  => 'Despacho Provincia',
            self::PROVINCE_CANCEL    => 'Cancelación Provincia',
            self::DISPATCH_ANNUL     => 'Anulación de Despacho',
            self::ECOMMERCE_RESERVE  => 'Reserva Ecommerce',
            self::ECOMMERCE_CANCEL   => 'Liberación Ecommerce',
            self::ECOMMERCE_DISPATCH => 'Despacho Ecommerce',
            self::PURCHASE_ENTRY     => 'Entrada por Compra',
            self::RETURN_RESTOCK     => 'Devolución — Reingreso Stock',
            self::RETURN_DAMAGED     => 'Devolución — Producto Dañado',
            self::ADJUSTMENT_IN      => 'Ajuste Ingreso',
            self::ADJUSTMENT_OUT     => 'Ajuste Egreso',
            self::TRANSFER_OUT       => 'Transferencia Salida',
            self::TRANSFER_IN        => 'Transferencia Entrada',
        };
    }

    /**
     * Delta que aplica a stock_physical (positivo = entrada, negativo = salida).
     */
    public function physicalDelta(float $qty): float
    {
        return match($this) {
            self::SALE_STORE,
            self::ADJUSTMENT_OUT,
            self::TRANSFER_OUT        => -abs($qty),

            self::PROVINCE_DISPATCH,
            self::ECOMMERCE_DISPATCH  => -abs($qty),

            self::SALE_STORE_RETURN,
            self::RETURN_RESTOCK,
            self::PURCHASE_ENTRY,
            self::ADJUSTMENT_IN,
            self::TRANSFER_IN,
            self::DISPATCH_ANNUL      => abs($qty),  // Anulación: devuelve stock físico

            // Province/Ecommerce commit/cancel solo afectan committed
            default                   => 0.0,
        };
    }

    /**
     * Delta que aplica a stock_committed (positivo = reserva, negativo = liberación).
     */
    public function committedDelta(float $qty): float
    {
        return match($this) {
            self::PROVINCE_COMMIT,
            self::ECOMMERCE_RESERVE  => abs($qty),

            self::PROVINCE_DISPATCH,
            self::ECOMMERCE_DISPATCH,
            self::PROVINCE_CANCEL,
            self::ECOMMERCE_CANCEL   => -abs($qty),

            self::DISPATCH_ANNUL     => abs($qty),  // Anulación: restaura committed

            default                  => 0.0,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
