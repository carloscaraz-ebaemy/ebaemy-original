<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instancia de Shalom Pro de la tienda (UUID de shalom-api.lat).
 *
 * Una instancia es la cuenta Shalom Pro de la tienda conectada al API: con ella
 * se crean guías antes de ir a la agencia y se baja el rótulo oficial. Hoy el
 * plan es Básico (0 instancias) y la columna queda vacía; cuando se contrate
 * Pro/Business cada tienda conecta la suya. Ver ShalomPlan::canCreateGuides().
 *
 * Cada tienda guarda desde ya su usuario y contraseña de Shalom Pro en su
 * política de envío: al activarse el plan, «Conectar» crea la instancia.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('shipping_settings')
            || $schema->hasColumn('shipping_settings', 'shalom_instance_id')) {
            return;
        }

        $schema->table('shipping_settings', function (Blueprint $table) {
            // Credenciales de la cuenta Shalom Pro DE LA TIENDA. La contraseña
            // va cifrada (cast `encrypted`) y nunca vuelve al navegador.
            $table->string('shalom_pro_user', 150)->nullable();
            $table->text('shalom_pro_password')->nullable();
            $table->string('shalom_instance_id', 64)->nullable();
            $table->timestamp('shalom_instance_connected_at')->nullable();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('shipping_settings')
            && $schema->hasColumn('shipping_settings', 'shalom_instance_id')) {
            $schema->table('shipping_settings', function (Blueprint $table) {
                $table->dropColumn(['shalom_pro_user', 'shalom_pro_password', 'shalom_instance_id', 'shalom_instance_connected_at']);
            });
        }
    }
};
