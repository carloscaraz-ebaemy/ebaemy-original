<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos de un envío (multipago).
 *
 * Un pedido se cobra en varias operaciones: el cliente paga un producto, luego
 * agrega otro y paga de nuevo. Cada pago tiene su MONTO y su CÓDIGO de
 * operación, y ningún código puede repetirse en toda la tienda.
 *
 * `shipping_requests.payment_code` se conserva (guarda el primer código) para
 * no romper el rótulo, el CSV ni los envíos ya confirmados; la fuente de verdad
 * pasa a ser esta tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('shipping_requests')) {
            return;
        }

        if (!Schema::connection('tenant')->hasTable('shipping_payments')) {
            Schema::connection('tenant')->create('shipping_payments', function (Blueprint $table) {
                $table->id();
                // shipping_requests usa $table->id() (bigint), no es una tabla
                // legacy: aqui SI corresponde unsignedBigInteger. `created_by`
                // en cambio apunta a `users`, que es legacy -> unsignedInteger.
                $table->unsignedBigInteger('shipment_id');
                $table->decimal('amount', 10, 2)->default(0);
                $table->string('payment_code', 60)->nullable();
                $table->string('payment_code_normalized', 60)->nullable();
                $table->string('method', 30)->nullable();
                $table->string('note', 255)->nullable();
                $table->dateTime('paid_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->string('created_by_name', 120)->nullable();
                $table->timestamps();

                $table->index('shipment_id');
                $table->index('payment_code_normalized');
            });
        }

        // Backfill: los pagos ya confirmados con código pasan a ser el primer
        // pago del envío, para que el historial no arranque vacío.
        if (Schema::connection('tenant')->hasColumn('shipping_requests', 'payment_code')) {
            $pendientes = DB::connection('tenant')->table('shipping_requests')
                ->whereNotNull('payment_code')
                ->where('payment_code', '!=', '')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))->from('shipping_payments')
                      ->whereColumn('shipping_payments.shipment_id', 'shipping_requests.id');
                })
                ->get(['id', 'payment_code', 'payment_code_normalized', 'payment_confirmed_at', 'payment_note', 'delivery_price']);

            foreach ($pendientes as $row) {
                DB::connection('tenant')->table('shipping_payments')->insert([
                    'shipment_id'             => $row->id,
                    // El monto no existía antes: queda en 0 y el operador lo
                    // corrige si lo necesita. Inventarlo seria peor.
                    'amount'                  => 0,
                    'payment_code'            => $row->payment_code,
                    'payment_code_normalized' => $row->payment_code_normalized,
                    'note'                    => $row->payment_note,
                    'paid_at'                 => $row->payment_confirmed_at,
                    'created_at'              => $row->payment_confirmed_at ?: now(),
                    'updated_at'              => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('shipping_payments');
    }
};
