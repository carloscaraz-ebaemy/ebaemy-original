<?php

namespace Tests\Unit;

use Tests\TestCase;

class AuditLogTest extends TestCase
{
    /** @test */
    public function audit_log_model_exists()
    {
        $this->assertTrue(class_exists(\App\Models\Tenant\AuditLog::class));
    }

    /** @test */
    public function audit_log_has_correct_fillable()
    {
        $model = new \App\Models\Tenant\AuditLog();
        $fillable = $model->getFillable();

        $this->assertContains('user_id', $fillable);
        $this->assertContains('action', $fillable);
        $this->assertContains('module', $fillable);
        $this->assertContains('ip_address', $fillable);
        $this->assertContains('old_values', $fillable);
        $this->assertContains('new_values', $fillable);
    }

    /** @test */
    public function audit_log_casts_json_fields()
    {
        $model = new \App\Models\Tenant\AuditLog();
        $casts = $model->getCasts();

        $this->assertEquals('array', $casts['old_values']);
        $this->assertEquals('array', $casts['new_values']);
    }
}
