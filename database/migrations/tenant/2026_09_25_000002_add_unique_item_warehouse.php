<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Devuelve el índice ÚNICO (item_id, warehouse_id) a los 9 tenants que lo perdieron.
 *
 * Va aparte de 2026_09_25_000001 por dos razones. Aquélla ya corrió, y sobre
 * todo éste no es un índice de rendimiento: es una **restricción de integridad**
 * sobre `item_warehouse`, que es donde vive la verdad del stock. Sin él, nada
 * impide dos filas para el mismo producto en el mismo almacén, y ese es
 * justamente uno de los modos en que el stock deja de cuadrar sin que ningún
 * error lo delate.
 *
 * Lo tienen los 10 tenants aprovisionados de cero y les falta a los 9 antiguos,
 * el mismo corte que los índices de rendimiento y por la misma causa: la
 * migración de marzo se tragaba el fallo.
 *
 * Comprobado antes de escribir esto: **cero pares duplicados en los 19
 * tenants**. El índice entra sin tener que decidir qué fila sobra, que es la
 * parte que no se puede automatizar. Si aun así apareciera un duplicado en el
 * momento de crearlo, se registra como `error` con el tenant y NO se inventa un
 * criterio para borrar: el stock no se arregla a ciegas.
 */
return new class extends Migration
{
    private const NOMBRE = 'idx_iw_item_warehouse';

    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('item_warehouse')) {
            return;
        }

        foreach (['item_id', 'warehouse_id'] as $col) {
            if (!$schema->hasColumn('item_warehouse', $col)) {
                \Log::error("[indices] item_warehouse: falta la columna {$col}, no se crea el unico");
                return;
            }
        }

        if ($this->existe()) {
            return;
        }

        // Nunca crear un UNIQUE sin mirar antes: si hubiera duplicados, el
        // ALTER falla y lo que hace falta es decidir a mano qué fila sobra.
        $duplicados = DB::connection('tenant')->select(
            'SELECT item_id, warehouse_id, COUNT(*) n
               FROM item_warehouse
              GROUP BY item_id, warehouse_id
             HAVING n > 1'
        );

        if ($duplicados) {
            $detalle = implode(', ', array_map(
                fn ($d) => "item {$d->item_id}/almacen {$d->warehouse_id} x{$d->n}",
                array_slice($duplicados, 0, 5)
            ));
            \Log::error('[indices] item_warehouse tiene ' . count($duplicados)
                . " pares duplicados: no se crea el unico. Revisar: {$detalle}");
            return;
        }

        try {
            $schema->table('item_warehouse', function (Blueprint $t) {
                $t->unique(['item_id', 'warehouse_id'], self::NOMBRE);
            });
        } catch (\Throwable $e) {
            \Log::error('[indices] no se pudo crear el unico de item_warehouse: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('item_warehouse') && $this->existe()) {
            $schema->table('item_warehouse', function (Blueprint $t) {
                $t->dropUnique(self::NOMBRE);
            });
        }
    }

    private function existe(): bool
    {
        return count(DB::connection('tenant')
            ->select('SHOW INDEX FROM `item_warehouse` WHERE Key_name = ?', [self::NOMBRE])) > 0;
    }
};
