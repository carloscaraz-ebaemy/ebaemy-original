<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4 — pone al día los envíos que ya estaban cobrados.
 *
 * Desde ahora, registrar el primer pago mueve el envío de «pendiente de
 * revisión» a «pendiente de remisión» (`ShipmentController::syncPaymentState`).
 * Sin esto, los que se cobraron ANTES del cambio se quedarían para siempre en
 * el estado viejo y la funcionalidad pareceria rota el primer dia: envíos
 * pagados que no aparecen como trabajo pendiente en ninguna parte.
 *
 * Mueve exactamente los que la regla nueva habría movido, ni uno más:
 * en `recibido`, con pago confirmado y sin anular. En producción son 11.
 *
 * No toca ningún otro estado. Un envío ya preparado, despachado o entregado no
 * retrocede, y un anulado se queda anulado.
 *
 * Idempotente: la segunda pasada no encuentra nada.
 */
class AdvancePaidShipmentsToPendingRemission extends Migration
{
    public function up()
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('shipping_requests')
            || !$schema->hasColumn('shipping_requests', 'payment_confirmed')) {
            return;
        }

        $db = DB::connection('tenant');

        $q = $db->table('shipping_requests')
                ->where('status', 'recibido')
                ->where('payment_confirmed', true);

        // `cancelled_at` llegó en una migración posterior a la tabla: en un
        // tenant que aún no la tenga, filtrar por ella sería un 1054.
        if ($schema->hasColumn('shipping_requests', 'cancelled_at')) {
            $q->whereNull('cancelled_at');
        }

        $q->update(['status' => 'confirmado', 'updated_at' => now()]);
    }

    /**
     * Devuelve a «recibido» solo lo que esta migración pudo mover. No es
     * perfecto —un envío cobrado y confirmado a mano se vería igual— pero
     * revertir es un caso de emergencia y el estado no destruye informacion:
     * el pago sigue registrado.
     */
    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('shipping_requests')) {
            return;
        }

        DB::connection('tenant')->table('shipping_requests')
          ->where('status', 'confirmado')
          ->where('payment_confirmed', true)
          ->update(['status' => 'recibido', 'updated_at' => now()]);
    }
}
