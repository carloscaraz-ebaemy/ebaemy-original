<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los pagos del envío se emparejan con los de Nota de Venta.
 *
 * `shipping_payments` ya tenía multipago con fecha, código, monto, método y
 * nota. Le faltaban tres cosas que Nota de Venta sí tiene y que el operador
 * necesita para cuadrar la caja:
 *
 *  - `payment_destination_id`: a qué caja o cuenta bancaria entró la plata.
 *    Mismo criterio que en order_payments: string, porque acepta "cash"
 *    además de un bank_account_id numérico.
 *  - `payment_method_type_id`: el método desde el catálogo (efectivo,
 *    transferencia, Yape…) en vez del texto libre de `method`. La columna
 *    `method` NO se toca: los pagos ya cargados la usan y el modal la sigue
 *    mostrando cuando el nuevo campo está vacío.
 *
 * El archivo adjunto no necesita columna: va por `payment_files` con relación
 * polimórfica, igual que en nota de venta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('shipping_payments')) {
            return;
        }

        Schema::connection('tenant')->table('shipping_payments', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('shipping_payments', 'payment_method_type_id')) {
                $table->char('payment_method_type_id', 2)->nullable()->after('method');
            }
            if (!Schema::connection('tenant')->hasColumn('shipping_payments', 'payment_destination_id')) {
                $table->string('payment_destination_id', 50)->nullable()->after('payment_method_type_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('shipping_payments')) {
            return;
        }

        Schema::connection('tenant')->table('shipping_payments', function (Blueprint $table) {
            foreach (['payment_method_type_id', 'payment_destination_id'] as $column) {
                if (Schema::connection('tenant')->hasColumn('shipping_payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
