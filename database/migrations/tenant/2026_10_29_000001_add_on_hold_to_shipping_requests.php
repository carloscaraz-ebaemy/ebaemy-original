<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido «en espera / acumulando productos».
 *
 * - `on_hold_since`: desde cuándo está en espera; mientras tenga valor, el
 *   reloj de días hábiles se detiene. NULL = no está en espera.
 * - `aging_from`: desde cuándo corre el reloj de días hábiles. NULL = desde
 *   `created_at` (comportamiento anterior).
 *
 * Idempotente: el módulo de Envíos no está en todos los tenants.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('shipping_requests')) {
            return;
        }

        if (!$schema->hasColumn('shipping_requests', 'on_hold_since')) {
            $schema->table('shipping_requests', function (Blueprint $table) {
                $table->dateTime('on_hold_since')->nullable();
            });
        }

        if (!$schema->hasColumn('shipping_requests', 'aging_from')) {
            $schema->table('shipping_requests', function (Blueprint $table) {
                $table->dateTime('aging_from')->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('shipping_requests')) {
            return;
        }

        foreach (['on_hold_since', 'aging_from'] as $column) {
            if ($schema->hasColumn('shipping_requests', $column)) {
                $schema->table('shipping_requests', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
