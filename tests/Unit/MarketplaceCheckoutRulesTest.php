<?php

namespace Tests\Unit;

use App\Services\System\MarketplaceCheckoutRules;
use PHPUnit\Framework\TestCase;

class MarketplaceCheckoutRulesTest extends TestCase
{
    public function test_telefono_se_normaliza_a_nueve_digitos(): void
    {
        $this->assertSame('987654321', MarketplaceCheckoutRules::normalizePhone('+51 987 654 321'));
        $this->assertSame('987654321', MarketplaceCheckoutRules::normalizePhone('987654321'));
        $this->assertNull(MarketplaceCheckoutRules::normalizePhone('887654321'));
        $this->assertNull(MarketplaceCheckoutRules::normalizePhone('98765'));
    }

    public function test_documentos_por_tipo(): void
    {
        $this->assertNull(MarketplaceCheckoutRules::docError('DNI', '12345678'));
        $this->assertNotNull(MarketplaceCheckoutRules::docError('DNI', '1234567'));
        $this->assertNull(MarketplaceCheckoutRules::docError('RUC', '20123456789'));
        $this->assertNotNull(MarketplaceCheckoutRules::docError('RUC', ''));
        $this->assertNull(MarketplaceCheckoutRules::docError('DNI', ''));
        $this->assertNull(MarketplaceCheckoutRules::docError('CE', '001234567'));
        $this->assertNotNull(MarketplaceCheckoutRules::docError('CE', '12345'));
        $this->assertNull(MarketplaceCheckoutRules::docError('Pasaporte', 'ab-123456'));
    }

    public function test_pasaporte_y_ce_conservan_las_letras(): void
    {
        $this->assertSame('AB123456', MarketplaceCheckoutRules::normalizeDocNumber('Pasaporte', 'ab-123456'));
        $this->assertSame('X1234567', MarketplaceCheckoutRules::normalizeDocNumber('CE', 'x 1234567'));
        $this->assertSame('12345678', MarketplaceCheckoutRules::normalizeDocNumber('DNI', '1234-5678'));
        $this->assertSame('pasaporte', MarketplaceCheckoutRules::shippingDocType('Pasaporte'));
    }
}
