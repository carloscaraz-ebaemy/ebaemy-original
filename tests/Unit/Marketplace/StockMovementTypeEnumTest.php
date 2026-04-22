<?php

namespace Tests\Unit\Marketplace;

use App\Enums\StockMovementTypeEnum;
use PHPUnit\Framework\TestCase;

/**
 * Tests de los deltas del enum — crítico porque applyStockMovement()
 * depende de que cada tipo devuelva el signo correcto al stock_physical
 * y stock_committed.
 */
class StockMovementTypeEnumTest extends TestCase
{
    public function test_sale_store_decrementa_physical_sin_afectar_committed()
    {
        $type = StockMovementTypeEnum::SALE_STORE;

        $this->assertSame(-5.0, $type->physicalDelta(5));
        $this->assertSame(0.0, $type->committedDelta(5));
    }

    public function test_ecommerce_reserve_incrementa_committed_sin_afectar_physical()
    {
        $type = StockMovementTypeEnum::ECOMMERCE_RESERVE;

        $this->assertSame(0.0, $type->physicalDelta(3));
        $this->assertSame(3.0, $type->committedDelta(3));
    }

    public function test_ecommerce_dispatch_decrementa_ambos()
    {
        $type = StockMovementTypeEnum::ECOMMERCE_DISPATCH;

        $this->assertSame(-4.0, $type->physicalDelta(4));
        $this->assertSame(-4.0, $type->committedDelta(4));
    }

    public function test_adjustment_in_agrega_physical()
    {
        $type = StockMovementTypeEnum::ADJUSTMENT_IN;

        $this->assertSame(10.0, $type->physicalDelta(10));
        $this->assertSame(0.0, $type->committedDelta(10));
    }

    public function test_adjustment_out_resta_physical()
    {
        $type = StockMovementTypeEnum::ADJUSTMENT_OUT;

        $this->assertSame(-7.0, $type->physicalDelta(7));
        $this->assertSame(0.0, $type->committedDelta(7));
    }

    public function test_transfer_out_in_se_cancelan_en_total()
    {
        $qty = 5;
        $out = StockMovementTypeEnum::TRANSFER_OUT->physicalDelta($qty);
        $in  = StockMovementTypeEnum::TRANSFER_IN->physicalDelta($qty);

        $this->assertSame(0.0, $out + $in);
    }

    public function test_deltas_siempre_usan_valor_absoluto()
    {
        // Aunque pasen qty negativa, el enum debe tratarla como absoluta
        // para prevenir operadores invertidos que causen stock negativo erróneo.
        $this->assertSame(-5.0, StockMovementTypeEnum::SALE_STORE->physicalDelta(-5));
        $this->assertSame(5.0, StockMovementTypeEnum::PURCHASE_ENTRY->physicalDelta(-5));
    }

    public function test_return_damaged_no_afecta_stock()
    {
        $type = StockMovementTypeEnum::RETURN_DAMAGED;

        $this->assertSame(0.0, $type->physicalDelta(10));
        $this->assertSame(0.0, $type->committedDelta(10));
    }

    public function test_dispatch_annul_revierte_dispatch()
    {
        // DISPATCH_ANNUL + PROVINCE_DISPATCH deben sumarse a 0 en ambos campos
        $qty = 5;
        $dispPhys = StockMovementTypeEnum::PROVINCE_DISPATCH->physicalDelta($qty);
        $dispComm = StockMovementTypeEnum::PROVINCE_DISPATCH->committedDelta($qty);

        $annulPhys = StockMovementTypeEnum::DISPATCH_ANNUL->physicalDelta($qty);
        $annulComm = StockMovementTypeEnum::DISPATCH_ANNUL->committedDelta($qty);

        $this->assertSame(0.0, $dispPhys + $annulPhys);
        $this->assertSame(0.0, $dispComm + $annulComm);
    }
}
