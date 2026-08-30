<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fechas de negocio del pedido (unificación Pedidos ↔ Envíos).
 *
 * `orders` ya tenía las fechas de la fase logística (prepared_at, dispatched_at,
 * delivered_at) pero le faltaban las comerciales, y el listado las estaba
 * supliendo con `updated_at`. `updated_at` NO es una fecha de negocio: cualquier
 * edición la mueve, así que "pedidos pagados ayer" devolvía cualquier cosa.
 *
 * Todas nullable y aditivas:
 *   - Los pedidos históricos quedan con NULL. Es correcto: no sabemos cuándo se
 *     pagaron, y rellenarlo con una fecha inventada sería peor que no tenerla.
 *   - Ningún código existente escribe estas columnas, así que nada cambia de
 *     comportamiento por aplicar esta migración.
 */
return new class extends Migration
{
    /** Columna => posición después de la cual insertarla. */
    private array $columns = [
        'paid_at'      => 'payment_status',
        'confirmed_at' => 'paid_at',
        'cancelled_at' => 'confirmed_at',
    ];

    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('orders')) {
            return;
        }

        Schema::connection('tenant')->table('orders', function (Blueprint $table) {
            foreach ($this->columns as $column => $after) {
                if (Schema::connection('tenant')->hasColumn('orders', $column)) {
                    continue;
                }

                // `after` es best-effort: si la columna de referencia no existe
                // en este tenant (migraciones aplicadas en distinto orden), se
                // agrega al final. El orden físico no afecta a nada.
                $definition = $table->timestamp($column)->nullable();
                if (Schema::connection('tenant')->hasColumn('orders', $after)) {
                    $definition->after($after);
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('orders')) {
            return;
        }

        Schema::connection('tenant')->table('orders', function (Blueprint $table) {
            foreach (array_keys($this->columns) as $column) {
                if (Schema::connection('tenant')->hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
