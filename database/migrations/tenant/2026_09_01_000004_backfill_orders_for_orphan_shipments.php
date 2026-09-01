<?php

use App\Models\Tenant\ShippingRequest;
use App\Services\Tenant\OrderShipmentLinker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * A-08 de la auditoría de Pedidos — los envíos sueltos pasan a tener pedido.
 *
 * Diagnóstico del 2026-09-01: 268 envíos en producción y **ninguno** enlazado a
 * un pedido; 267 sin siquiera un pedido candidato. No eran enlaces perdidos que
 * `shipments:reconcile` pudiera reencontrar: eran encargos que entraron por
 * `/registro-envio`, que no es un módulo residual sino la puerta de entrada
 * real de la logística en tres tenants.
 *
 * Consecuencia: toda la mitad logística del panel unificado estaba a cero,
 * porque sus filtros cuelgan de `whereHas('shipments')` y ningún pedido tenía
 * envío. Los chips «por imprimir», «por embalar», «por despachar», «en
 * tránsito» y «listos para recojo» marcaban 0 en los cuatro tenants con
 * pedidos, y `sin_envio` marcaba el total.
 *
 * Decisión tomada: el encargo logístico ES un pedido, aunque no lleve productos
 * ni importe. Esta migración crea el pedido espejo de cada envío huérfano.
 *
 * Detalles que importan:
 *   - La fecha del pedido es la del ENVÍO, no la de la migración: si no, los
 *     268 aterrizarían todos en el día del deploy y destrozarían cualquier
 *     lectura temporal del panel.
 *   - Los pedidos nacen con total 0 y canal propio (`ENV01`, tipo `other`), así
 *     que no contaminan la facturación ni las métricas de venta. Sí suben el
 *     CONTEO de pedidos, que es lo correcto: son trabajo real.
 *   - Los envíos ANULADOS se saltan: crearles un pedido resucitaría en la cola
 *     un encargo que alguien dio de baja.
 *
 * Idempotente: `ensureOrderFor()` devuelve el pedido existente si ya lo hay, y
 * la consulta solo mira envíos con `order_id` nulo.
 */
class BackfillOrdersForOrphanShipments extends Migration
{
    public function up()
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('shipping_requests')
            || !$schema->hasTable('orders')
            || !$schema->hasTable('sales_channels')) {
            return;
        }

        $linker = app(OrderShipmentLinker::class);
        $hechos = 0;
        $fallos = 0;

        // `cancelled_at` llegó en una migración posterior a la tabla: en un
        // tenant que aún no la tenga, filtrar por ella sería un 1054.
        $tieneAnulados = $schema->hasColumn('shipping_requests', 'cancelled_at');

        ShippingRequest::query()
            ->whereNull('order_id')
            ->when($tieneAnulados, fn ($q) => $q->whereNull('cancelled_at'))
            ->orderBy('id')
            ->chunkById(200, function ($envios) use ($linker, &$hechos, &$fallos) {
                foreach ($envios as $envio) {
                    try {
                        $linker->ensureOrderFor($envio);
                        $hechos++;
                    } catch (\Throwable $e) {
                        // Un envío con datos raros no puede abortar el backfill
                        // de los otros 267.
                        $fallos++;
                        Log::error('backfill: no se pudo crear el pedido del envio', [
                            'shipment_id' => $envio->id,
                            'error'       => $e->getMessage(),
                        ]);
                    }
                }
            });

        if ($hechos || $fallos) {
            Log::info("backfill envios→pedidos: {$hechos} creados, {$fallos} fallidos");
        }
    }

    /**
     * No revierte. Borrar los pedidos creados dejaría de nuevo 268 encargos
     * invisibles en la única pantalla donde se los busca, y no hay forma segura
     * de distinguir los que esta migración creó de los que se hayan generado
     * después por el flujo normal.
     */
    public function down()
    {
        //
    }
}
