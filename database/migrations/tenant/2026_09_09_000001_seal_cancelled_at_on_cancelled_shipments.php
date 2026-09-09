<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sella `cancelled_at` en los envíos que solo tenían el `status` en anulado.
 *
 * La columna se añadió después de que existiera el estado, así que los envíos
 * anulados hasta entonces quedaron con `status = 'anulado'` y la fecha en NULL.
 * Eso dejó DOS criterios de anulado conviviendo: el accesor `is_cancelled`
 * miraba el estado, y las consultas que responden «cuál es el envío de este
 * pedido» miraban la fecha. Resultado: esos envíos seguían saliendo en el panel
 * de Pedidos como la entrega vigente, con todas sus acciones disponibles.
 *
 * El código ya no depende de esto —`scopeVigente()` mira las dos cosas— pero el
 * dato tenía que quedar coherente igual: cualquier consulta futura, un informe
 * o una exportación que pregunte por `cancelled_at` estaría contando mal.
 *
 * Se usa `updated_at` como fecha de anulación, que es lo más cercano a la
 * verdad que hay: la anulación fue la última escritura de esas filas. Cuando
 * falta, se cae a `created_at`. NO se inventa `now()`: fecharlas hoy diría que
 * se anularon el día del deploy.
 *
 * Idempotente: solo toca filas con la fecha en NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shipping_requests')
            || !Schema::hasColumn('shipping_requests', 'cancelled_at')) {
            return;
        }

        DB::table('shipping_requests')
            ->where('status', 'anulado')
            ->whereNull('cancelled_at')
            ->update([
                'cancelled_at' => DB::raw('COALESCE(updated_at, created_at)'),
            ]);
    }

    public function down(): void
    {
        // Sin vuelta atrás: no se puede distinguir la fecha que sella esta
        // migración de la que escribió una anulación real, y borrar las dos
        // devolvería el bug en vez de deshacer el cambio.
    }
};
