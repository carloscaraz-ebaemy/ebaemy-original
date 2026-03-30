<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs')) return;

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_type', 50)->nullable(); // admin, seller, system
            $table->string('action', 50); // create, update, delete, login, logout, export
            $table->string('module', 80); // documents, items, orders, auth, config
            $table->string('description', 500)->nullable();
            $table->string('auditable_type', 150)->nullable(); // Model class
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'idx_audit_user_date');
            $table->index(['module', 'action', 'created_at'], 'idx_audit_module_action');
            $table->index(['auditable_type', 'auditable_id'], 'idx_audit_auditable');
            $table->index('created_at', 'idx_audit_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
