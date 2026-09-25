<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crea los índices de rendimiento que 2026_03_27_000001 dio por creados y no creó.
 *
 * Aquella migración consta como ejecutada en los 19 tenants, pero los índices
 * sólo existen en los 10 que se aprovisionaron de cero (batch 1). En los 9
 * antiguos —alasitas, makingroup, mitienda, talara, myka, torneo, calixto,
 * gabito, torneoperu, que son justo los que más datos tienen— no hay NINGUNO
 * de los 20. Auditado tenant por tenant el 2026-09-25.
 *
 * Se tragaba el fallo: su `safeIndex()` captura cualquier excepción y sigue,
 * así que la migración terminaba «bien» y quedaba registrada. Nadie podía
 * notarlo porque un índice que falta no rompe nada, sólo hace lento lo que
 * debería ser rápido.
 *
 * Dos cosas se corrigen además de crearlos:
 *
 *  - `documents.person_id` NO EXISTE en ningún tenant: la columna del cliente
 *    en esa tabla es `customer_id`. Ese índice llevaba desde marzo fallando en
 *    los 19 y dejando un aviso en cada aprovisionamiento.
 *  - Aquí se comprueba que las columnas existan ANTES de pedir el índice, y lo
 *    que no se puede crear se dice con `error`, no con `warning`: un índice de
 *    rendimiento que no se crea es una promesa incumplida, no una anécdota.
 *
 * Idempotente: comprueba tabla, columnas e índice antes de tocar nada.
 */
return new class extends Migration
{
    /** @var array<string, array<string, string[]>> tabla => [nombre => columnas] */
    private const INDICES = [
        'documents' => [
            // `customer_id`, no `person_id`: ese era el error de la original.
            'idx_docs_customer_date'   => ['customer_id', 'date_of_issue'],
            'idx_docs_state_date'      => ['state_type_id', 'date_of_issue'],
            'idx_docs_user_date'       => ['user_id', 'date_of_issue'],
            'idx_docs_type_series_num' => ['document_type_id', 'series', 'number'],
        ],
        'sale_notes' => [
            'idx_sn_customer_date'    => ['customer_id', 'date_of_issue'],
            'idx_sn_state_date'       => ['state_type_id', 'date_of_issue'],
            'idx_sn_logistic'         => ['logistic_status', 'requires_warehouse_dispatch'],
            'idx_sn_warehouse_status' => ['warehouse_id', 'logistic_status'],
        ],
        'orders' => [
            'idx_orders_status_created'  => ['status_order_id', 'created_at'],
            'idx_orders_channel_created' => ['channel_id', 'created_at'],
            'idx_orders_person'          => ['person_id'],
        ],
        'stock_movements' => [
            'idx_sm_item_wh_date' => ['item_id', 'warehouse_id', 'created_at'],
            'idx_sm_type_date'    => ['type', 'created_at'],
            'idx_sm_reference'    => ['reference_type', 'reference_id'],
        ],
        'persons' => [
            'idx_persons_type_name' => ['type', 'name'],
            'idx_persons_number'    => ['number'],
        ],
        'inventory_kardex' => [
            'idx_kardex_item_date' => ['item_id', 'date_of_issue'],
            'idx_kardex_wh_date'   => ['warehouse_id', 'date_of_issue'],
        ],
        'abandoned_carts' => [
            'idx_ac_status' => ['recovered_at', 'expires_at'],
            'idx_ac_email'  => ['customer_email'],
        ],
    ];

    public function up(): void
    {
        $schema = Schema::connection('tenant');
        $creados = 0;

        foreach (self::INDICES as $tabla => $indices) {
            if (!$schema->hasTable($tabla)) {
                continue;   // el tenant no tiene ese módulo
            }

            foreach ($indices as $nombre => $columnas) {
                // Las columnas primero: pedir un índice sobre una que no existe
                // es exactamente como nació el fallo que esto viene a arreglar.
                $faltan = array_values(array_filter(
                    $columnas,
                    fn ($c) => !$schema->hasColumn($tabla, $c)
                ));

                if ($faltan) {
                    \Log::error("[indices] {$tabla}.{$nombre} no se crea: falta la columna "
                        . implode(', ', $faltan));
                    continue;
                }

                if ($this->existe($tabla, $nombre)) {
                    continue;
                }

                try {
                    $schema->table($tabla, function (Blueprint $t) use ($columnas, $nombre) {
                        $t->index($columnas, $nombre);
                    });
                    $creados++;
                } catch (\Throwable $e) {
                    // `error` y no `warning`: si esto falla, la consulta que el
                    // índice venía a sostener se queda sin él y nadie se entera.
                    \Log::error("[indices] no se pudo crear {$tabla}.{$nombre}: " . $e->getMessage());
                }
            }
        }

        if ($creados) {
            \Log::info("[indices] creados {$creados} indices de rendimiento que faltaban");
        }
    }

    /**
     * El `down` sólo quita el índice nuevo de documents.
     *
     * Los demás los declaró la migración de marzo: si se revierte ESTA, los
     * tenants nuevos se quedarían sin unos índices que nunca fueron suyos, y
     * volver a correr aquélla no los recrearía porque ya consta ejecutada.
     */
    public function down(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('documents') && $this->existe('documents', 'idx_docs_customer_date')) {
            $schema->table('documents', function (Blueprint $t) {
                $t->dropIndex('idx_docs_customer_date');
            });
        }
    }

    /** ¿Existe ya ese índice? `SHOW INDEX` es lo que entiende MySQL 8 aquí. */
    private function existe(string $tabla, string $nombre): bool
    {
        return count(DB::connection('tenant')
            ->select("SHOW INDEX FROM `{$tabla}` WHERE Key_name = ?", [$nombre])) > 0;
    }
};
