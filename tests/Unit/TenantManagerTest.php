<?php

namespace Tests\Unit;

use App\Services\TenantManager;
use Tests\TestCase;

class TenantManagerTest extends TestCase
{
    protected TenantManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new TenantManager();
    }

    public function test_check_returns_false_without_tenant(): void
    {
        // En entorno de test no hay tenant resuelto
        $this->assertFalse($this->manager->check());
    }

    public function test_id_returns_null_without_tenant(): void
    {
        $this->assertNull($this->manager->id());
    }

    public function test_company_returns_null_without_tenant(): void
    {
        $this->assertNull($this->manager->company());
        $this->assertNull($this->manager->company('name'));
    }

    public function test_config_returns_null_without_tenant(): void
    {
        $this->assertNull($this->manager->config());
    }

    public function test_state_returns_pending_without_tenant(): void
    {
        $this->assertEquals(TenantManager::STATE_PENDING, $this->manager->state());
    }

    public function test_is_active_returns_false_without_tenant(): void
    {
        $this->assertFalse($this->manager->isActive());
    }

    public function test_clean_host_removes_www_and_port(): void
    {
        $ref = new \ReflectionMethod($this->manager, 'cleanHost');
        $ref->setAccessible(true);

        $this->assertEquals('example.com', $ref->invoke($this->manager, 'www.example.com'));
        $this->assertEquals('example.com', $ref->invoke($this->manager, 'example.com:8080'));
        $this->assertEquals('example.com', $ref->invoke($this->manager, 'WWW.EXAMPLE.COM'));
        $this->assertEquals('sub.example.com', $ref->invoke($this->manager, 'www.sub.example.com'));
    }

    public function test_has_feature_returns_false_without_tenant(): void
    {
        $this->assertFalse($this->manager->hasFeature('ecommerce'));
    }

    public function test_ecommerce_setting_returns_default_without_tenant(): void
    {
        $this->assertEquals('fallback', $this->manager->ecommerceSetting('color_ecommerce', 'fallback'));
    }

    public function test_flush_domain_cache_does_not_throw(): void
    {
        // Should not throw even without Redis or cached data
        $this->manager->flushDomainCache('test.example.com');
        $this->assertTrue(true);
    }
}
