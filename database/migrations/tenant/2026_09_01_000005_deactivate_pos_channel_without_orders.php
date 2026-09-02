<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A-09 de la auditoría de Pedidos — el canal «Punto de Venta» prometía algo
 * que nunca iba a ocurrir.
 *
 * Decisión de perímetro tomada el 2026-09-01: **Pedidos es la venta que
 * REQUIERE ENTREGA**. Una venta de mostrador ya está entregada en el momento en
 * que ocurre —no hay nada que preparar, imprimir, despachar ni entregar— así
 * que sigue viviendo en `documents` / `sale_notes` y no genera `Order`.
 *
 * El problema es que `POS01` se sembraba **activo** en todos los tenants, y
 * `is_active` es lo que alimentan `OrderController::channels()` (el desplegable
 * del filtro de Pedidos) y `DiscountRuleController` (el selector de canal de
 * una regla de descuento). Resultado: 17 tenants con un canal a cero pedidos
 * ofrecido para filtrar, y la posibilidad de crear una regla de descuento
 * dirigida a un canal que jamás dispara.
 *
 * Se desactiva SOLO donde no tiene pedidos. Si algún tenant llegara a tenerlos
 * —hoy no los hay en ninguno— el canal se respeta: la migración no puede
 * decidir que un dato real sobra.
 *
 * `WHA01` y `TEL01` ya se sembraban inactivos, así que quedan igual. `ECOM` se
 * mantiene activo aunque esté a cero en la mayoría: el checkout del ecommerce
 * sí puede producir pedidos en cualquier momento.
 *
 * Idempotente y reversible: solo mueve un flag.
 */
class DeactivatePosChannelWithoutOrders extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('sales_channels')
            || !Schema::connection('tenant')->hasColumn('orders', 'channel_id')) {
            return;
        }

        $db = DB::connection('tenant');

        $db->table('sales_channels')
           ->where('code', 'POS01')
           ->where('is_active', true)
           ->whereNotExists(fn ($q) => $q->selectRaw('1')
               ->from('orders')
               ->whereColumn('orders.channel_id', 'sales_channels.id'))
           ->update(['is_active' => false, 'updated_at' => now()]);
    }

    /**
     * Reactiva el canal. Es solo un flag, así que revertir es seguro — aunque
     * volvería a ofrecer en el filtro un canal que no produce pedidos.
     */
    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('sales_channels')) {
            return;
        }

        DB::connection('tenant')->table('sales_channels')
          ->where('code', 'POS01')
          ->update(['is_active' => true, 'updated_at' => now()]);
    }
}
