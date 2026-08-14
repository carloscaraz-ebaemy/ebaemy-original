<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campañas históricas y exclusión de ganadores previos.
 *
 * El sorteo exige que el participante haya ACEPTADO. Para las campañas de
 * meses anteriores eso es imposible: el mecanismo de aceptación no existía
 * todavía, así que nadie pudo aceptar.
 *
 * La salida NO es marcar el consentimiento a mano —seria inventar un
 * consentimiento que el cliente nunca dio—. Se declara que la campaña usa
 * criterios de elegibilidad del negocio (pago confirmado + despacho +
 * periodo) en vez del consentimiento, y queda registrado en la campaña.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('raffles')) {
            return;
        }

        Schema::connection('tenant')->table('raffles', function (Blueprint $table) {
            $has = fn ($c) => Schema::connection('tenant')->hasColumn('raffles', $c);

            // 'consent' = el cliente acepta por su enlace (lo normal).
            // 'historical' = elegibilidad por criterios del negocio.
            if (!$has('eligibility_mode')) {
                $table->string('eligibility_mode', 20)->default('consent')->after('source_filters');
            }

            // Un cliente que ya gano no vuelve a participar, salvo que el
            // administrador lo habilite (regla por defecto del negocio).
            if (!$has('exclude_past_winners')) {
                $table->boolean('exclude_past_winners')->default(true)->after('eligibility_mode');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('raffles')) {
            return;
        }

        Schema::connection('tenant')->table('raffles', function (Blueprint $table) {
            foreach (['exclude_past_winners', 'eligibility_mode'] as $c) {
                if (Schema::connection('tenant')->hasColumn('raffles', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
