<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persona que recoge el paquete (obligatoria cuando el cliente es EMPRESA).
 *
 * Las agencias de transporte no entregan a un RUC: exigen el DNI y el nombre de
 * una persona natural. Con RUC el cliente solo dejaba la razón social, así que
 * el rótulo salía sin nadie a quien entregarle.
 *
 * Idempotente por columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('shipping_requests')) {
            return;
        }

        Schema::connection('tenant')->table('shipping_requests', function (Blueprint $table) {
            $has = fn ($c) => Schema::connection('tenant')->hasColumn('shipping_requests', $c);
            if (!$has('pickup_person_name')) {
                $table->string('pickup_person_name', 160)->nullable()->after('document_type');
            }
            if (!$has('pickup_person_dni')) {
                $table->string('pickup_person_dni', 20)->nullable()->after('pickup_person_name');
            }
            if (!$has('pickup_person_phone')) {
                $table->string('pickup_person_phone', 20)->nullable()->after('pickup_person_dni');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('shipping_requests')) {
            return;
        }

        Schema::connection('tenant')->table('shipping_requests', function (Blueprint $table) {
            foreach (['pickup_person_name', 'pickup_person_dni', 'pickup_person_phone'] as $c) {
                if (Schema::connection('tenant')->hasColumn('shipping_requests', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
