<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oficina exacta de la agencia donde recoge el cliente.
 *
 * Hasta ahora era texto libre en `reference` («Shalom terminal», «la de la
 * av. grande»…). Con el catálogo de Shalom el cliente elige la oficina de una
 * lista y aquí queda su id (`ter_id` de Shalom, ver `shalom_agencies` en la
 * base del sistema) más una copia del nombre y la dirección: el envío tiene
 * que seguir diciendo a dónde fue aunque la oficina cierre o el catálogo se
 * pierda.
 *
 * `reference` se sigue llenando con el nombre de la oficina para que el
 * rótulo y los listados que ya la leen no cambien.
 *
 * Idempotente (`hasColumn`): el módulo de Envíos no está en todos los tenants.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('shipping_requests')
            || $schema->hasColumn('shipping_requests', 'agency_office_id')) {
            return;
        }

        $schema->table('shipping_requests', function (Blueprint $table) {
            $table->unsignedInteger('agency_office_id')->nullable()->after('reference');
            $table->string('agency_office_name', 160)->nullable()->after('agency_office_id');
            $table->string('agency_office_address', 500)->nullable()->after('agency_office_name');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('shipping_requests')
            && $schema->hasColumn('shipping_requests', 'agency_office_id')) {
            $schema->table('shipping_requests', function (Blueprint $table) {
                $table->dropColumn(['agency_office_id', 'agency_office_name', 'agency_office_address']);
            });
        }
    }
};
