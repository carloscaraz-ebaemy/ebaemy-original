<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verificación de dominios personalizados (BD sistema).
 * Permite a empresas usar su propio dominio (tienda.cliente.com).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('domain_verifications')) return;

        Schema::create('domain_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hostname_id');
            $table->string('domain', 255);
            $table->enum('method', ['dns_txt', 'dns_cname', 'file'])->default('dns_cname');
            $table->string('verification_token', 64);
            $table->enum('status', ['pending', 'verified', 'failed', 'expired'])->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();

            $table->index('domain');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_verifications');
    }
};
