<?php

namespace Tests\Unit\Marketplace;

use App\Enums\BusinessTypeEnum;
use PHPUnit\Framework\TestCase;

class BusinessTypeEnumTest extends TestCase
{
    public function test_has_ecommerce_solo_true_para_ecommerce()
    {
        $this->assertTrue(BusinessTypeEnum::ECOMMERCE->hasEcommerce());
        $this->assertFalse(BusinessTypeEnum::RETAIL->hasEcommerce());
        $this->assertFalse(BusinessTypeEnum::RESTAURANT->hasEcommerce());
        $this->assertFalse(BusinessTypeEnum::SERVICES->hasEcommerce());
    }

    public function test_required_modules_incluye_ecommerce_para_ecommerce()
    {
        $modules = BusinessTypeEnum::ECOMMERCE->requiredModules();
        $this->assertContains('ecommerce', $modules);
        $this->assertContains('inventory', $modules);
    }

    public function test_requires_warehouse_dispatch_para_rubros_fisicos()
    {
        $this->assertTrue(BusinessTypeEnum::ECOMMERCE->requiresWarehouseDispatch());
        $this->assertTrue(BusinessTypeEnum::LOGISTICS->requiresWarehouseDispatch());
        $this->assertTrue(BusinessTypeEnum::RETAIL->requiresWarehouseDispatch());
        $this->assertFalse(BusinessTypeEnum::SERVICES->requiresWarehouseDispatch());
        $this->assertFalse(BusinessTypeEnum::RESTAURANT->requiresWarehouseDispatch());
    }

    public function test_values_devuelve_todos_los_casos()
    {
        $values = BusinessTypeEnum::values();
        $this->assertContains('retail', $values);
        $this->assertContains('ecommerce', $values);
        $this->assertContains('restaurant', $values);
        $this->assertCount(6, $values);
    }
}
