<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguimiento de la guía en la agencia (hoy Shalom).
 *
 * `tracking_number` ya guardaba el N° de guía, pero Shalom exige además el
 * código de 4 caracteres impreso en su ticket para rastrearla. Con los dos se
 * consulta su estado (POST /track) y se guarda aquí el último hito y la línea
 * de tiempo, para mostrársela al cliente sin llamar al API en cada visita.
 *
 * Idempotente: el módulo de Envíos no está en todos los tenants.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('shipping_requests')
            || $schema->hasColumn('shipping_requests', 'tracking_code')) {
            return;
        }

        $schema->table('shipping_requests', function (Blueprint $table) {
            $table->string('tracking_code', 12)->nullable()->after('tracking_number');
            $table->string('carrier_status', 30)->nullable()->after('tracking_code');
            $table->json('carrier_events')->nullable()->after('carrier_status');
            $table->timestamp('carrier_checked_at')->nullable()->after('carrier_events');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('shipping_requests')
            && $schema->hasColumn('shipping_requests', 'tracking_code')) {
            $schema->table('shipping_requests', function (Blueprint $table) {
                $table->dropColumn(['tracking_code', 'carrier_status', 'carrier_events', 'carrier_checked_at']);
            });
        }
    }
};
