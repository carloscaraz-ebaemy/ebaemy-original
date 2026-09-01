<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A-03 de la auditoría de Pedidos — `payment_status` tenía dos significados.
 *
 * Había dos fuentes de verdad para el mismo hecho y cuatro vocabularios entre
 * seis escritores:
 *
 *   - `pending_capture` / `captured` / `capture_failed` — el ciclo de la
 *     pasarela (Culqi). Lo leen `OrderPolicy`, `OrderToSaleNoteService` y la
 *     confirmación del ecommerce: es vocabulario vivo y con consecuencias.
 *   - `paid` — lo escribían la importación de Saga y el dispatcher del
 *     marketplace para decir «este pedido ya está pagado». **Nadie lo leía.**
 *     Y ese hecho ya lo dice `status_order_id` (2 = pago verificado), que es lo
 *     que mueve toda la operación.
 *
 * Decisión aplicada: `payment_status` describe el estado del cobro EN LA
 * PASARELA y nada más. NULL significa «este cobro no pasó por una pasarela»
 * (efectivo, contra entrega, Saga —donde Falabella cobra y liquida fuera del
 * sistema—), NO significa «sin pagar».
 *
 * Esta migración retira el valor `paid` de lo ya grabado. Es seguro: ningún
 * lector del código lo consulta, y el estado comercial de esos pedidos no se
 * toca. En producción son los 630 pedidos de Saga de carolayimport.
 *
 * Lo que NO hace: inventar `paid_at` hacia atrás. Los pedidos nuevos de Saga sí
 * lo reciben (`ordered_at`), pero rellenar el histórico exigiría deducir una
 * fecha de cobro que no consta — el mismo criterio que con `channel_id`.
 *
 * Idempotente: la segunda pasada no encuentra nada que cambiar.
 */
class NormalizePaymentStatusVocabulary extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasColumn('orders', 'payment_status')) {
            return;
        }

        DB::connection('tenant')
          ->table('orders')
          ->where('payment_status', 'paid')
          ->update(['payment_status' => null]);
    }

    /**
     * No revierte. Devolver `paid` restauraría un valor que ningún lector
     * entiende y que duplica lo que ya dice `status_order_id`.
     */
    public function down()
    {
        //
    }
}
