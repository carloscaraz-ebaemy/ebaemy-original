<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cupones DE PLATAFORMA gestionados desde SuperAdmin.
 *
 * Distintos a `tenant.coupons` (publicos por tienda, codigo libre):
 * estos son asignables a usuarios especificos del marketplace y
 * pueden aplicar cross-tenant (scope platform) o a una sola
 * tienda (scope tenant).
 *
 * Las dos capas COEXISTEN en el checkout: el comprador puede
 * ingresar un coupon de tenant manualmente Y al mismo tiempo
 * tener cupones de plataforma asignados que se aplican auto.
 *
 * Fase posterior: tenants podran crear cupones DE PLATAFORMA
 * dirigidos a sus propios clientes desde su admin (mismo schema,
 * solo cambia el creator).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketplace_coupons')) return;

        Schema::create('marketplace_coupons', function (Blueprint $table) {
            $table->id();
            // Codigo legible/compartible. Unique para evitar colisiones
            // entre cupones de distintos creadores.
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            // percent: value entre 0-100. fixed: value en moneda.
            $table->enum('type', ['percent', 'fixed']);
            $table->decimal('value', 10, 2);
            // Reglas de aplicacion.
            $table->decimal('min_subtotal', 10, 2)->nullable()
                  ->comment('Subtotal minimo para aplicar (null = sin minimo)');
            $table->decimal('max_discount', 10, 2)->nullable()
                  ->comment('Solo si type=percent: cap absoluto del descuento');
            // Scope: platform = cualquier tienda, tenant = una sola.
            $table->enum('scope', ['platform', 'tenant'])->default('platform');
            $table->unsignedInteger('tenant_id')->nullable()
                  ->comment('hostname_id si scope=tenant');
            // Ventana de validez.
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            // Limites de redencion.
            $table->unsignedInteger('max_redemptions')->nullable()
                  ->comment('Total absoluto (null = ilimitado)');
            $table->unsignedInteger('max_per_user')->default(1)
                  ->comment('Cuantas veces puede usarlo el mismo user');
            // Trazabilidad.
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['scope', 'tenant_id'], 'mkt_coupons_scope_idx');
            $table->index(['is_active', 'valid_until'], 'mkt_coupons_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_coupons');
    }
};
