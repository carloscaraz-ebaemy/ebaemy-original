<?php

namespace Tests\Unit;

use Tests\TestCase;

class RbacTest extends TestCase
{
    /** @test */
    public function role_model_exists()
    {
        $this->assertTrue(class_exists(\App\Models\Tenant\Role::class));
    }

    /** @test */
    public function permission_model_exists()
    {
        $this->assertTrue(class_exists(\App\Models\Tenant\Permission::class));
    }

    /** @test */
    public function has_roles_trait_exists()
    {
        $this->assertTrue(trait_exists(\App\Traits\HasRoles::class));
    }

    /** @test */
    public function role_has_permissions_relationship()
    {
        $role = new \App\Models\Tenant\Role();
        $this->assertTrue(method_exists($role, 'permissions'));
    }

    /** @test */
    public function permission_has_module_scope()
    {
        $permission = new \App\Models\Tenant\Permission();
        $this->assertTrue(method_exists($permission, 'scopeByModule'));
    }
}
