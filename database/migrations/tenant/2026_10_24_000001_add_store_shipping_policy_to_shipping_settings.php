<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Política de envío de la tienda: UNA configuración que usan su tienda online,
 * el marketplace ebaemy.com y el catálogo de anuncios (Google/Meta).
 *
 * Hasta ahora el envío vivía en `shipping_zones` con los valores de ejemplo de
 * fábrica (Lima S/10 1 día, Provincias S/25 5 días) que ninguna tienda tocó, y
 * la zona Lima nunca coincidía (district_ids NULL): a un comprador de Lima se
 * le cobraba el envío de provincias. El marketplace no recibía nada.
 *
 * Modos:
 *  - lima_mode:     free | fixed | quote
 *  - province_mode: free | fixed | destination (la tienda lleva a la agencia —
 *                   cobra `agency_fee` según `agency_fee_mode`, columnas que ya
 *                   existen — y el flete lo paga el comprador al recoger) | quote
 *  `*_cost` es el costo fijo o, en «quote», el «desde S/».
 *
 * `shipping_confirmed_at` NULL = la tienda no lo ha revisado: no se muestra
 * nada como cierto y no se puede publicar en el marketplace.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('shipping_settings')) {
            return;
        }

        Schema::connection('tenant')->table('shipping_settings', function (Blueprint $table) {
            $s = Schema::connection('tenant');
            $add = function (string $col, callable $def) use ($s) {
                if (!$s->hasColumn('shipping_settings', $col)) {
                    $def();
                }
            };

            $add('lima_mode', fn () => $table->string('lima_mode', 20)->nullable());
            $add('lima_cost', fn () => $table->decimal('lima_cost', 8, 2)->nullable());
            $add('lima_days_min', fn () => $table->unsignedTinyInteger('lima_days_min')->nullable());
            $add('lima_days_max', fn () => $table->unsignedTinyInteger('lima_days_max')->nullable());

            $add('province_mode', fn () => $table->string('province_mode', 20)->nullable());
            $add('province_cost', fn () => $table->decimal('province_cost', 8, 2)->nullable());
            $add('province_handling_days', fn () => $table->unsignedTinyInteger('province_handling_days')->nullable());
            $add('province_transit_min', fn () => $table->unsignedTinyInteger('province_transit_min')->nullable());
            $add('province_transit_max', fn () => $table->unsignedTinyInteger('province_transit_max')->nullable());
            $add('province_agencies', fn () => $table->json('province_agencies')->nullable());

            $add('pickup_enabled', fn () => $table->boolean('pickup_enabled')->default(false));
            $add('pickup_district', fn () => $table->string('pickup_district', 120)->nullable());
            $add('pickup_ready_hours', fn () => $table->unsignedSmallInteger('pickup_ready_hours')->nullable());

            $add('shipping_confirmed_at', fn () => $table->timestamp('shipping_confirmed_at')->nullable());
        });
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('shipping_settings')) {
            return;
        }

        $cols = array_values(array_filter([
            'lima_mode', 'lima_cost', 'lima_days_min', 'lima_days_max',
            'province_mode', 'province_cost', 'province_handling_days', 'province_transit_min',
            'province_transit_max', 'province_agencies', 'pickup_enabled', 'pickup_district',
            'pickup_ready_hours', 'shipping_confirmed_at',
        ], fn ($c) => Schema::connection('tenant')->hasColumn('shipping_settings', $c)));

        if ($cols) {
            Schema::connection('tenant')->table('shipping_settings', fn (Blueprint $t) => $t->dropColumn($cols));
        }
    }
};
