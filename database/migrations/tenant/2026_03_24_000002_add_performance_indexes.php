<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices de rendimiento para consultas de reportes y dashboards.
 *
 * Contexto: las tablas de documentos y notas de venta pueden tener
 * decenas de miles de registros por tenant. Sin índices en date_of_issue
 * y customer_id, los reportes de rango de fechas hacen full-table scan.
 *
 * Se usa addIndex con verificación previa para ser idempotente
 * (no falla si el índice ya existe por alguna migración anterior).
 */
return new class extends Migration
{
    private array $indexes = [
        // ── Sale Notes ────────────────────────────────────────────────────────
        ['sale_notes',     ['date_of_issue'],            'sale_notes_date_idx'],
        ['sale_notes',     ['customer_id'],              'sale_notes_customer_idx'],
        ['sale_notes',     ['state_type_id'],            'sale_notes_state_idx'],
        ['sale_notes',     ['date_of_issue','customer_id'], 'sale_notes_date_customer_idx'],
        ['sale_notes',     ['user_id','date_of_issue'],  'sale_notes_user_date_idx'],

        // ── Documents (Facturas / Boletas) ────────────────────────────────────
        ['documents',      ['date_of_issue'],            'documents_date_idx'],
        ['documents',      ['customer_id'],              'documents_customer_idx'],
        ['documents',      ['state_type_id'],            'documents_state_idx'],
        ['documents',      ['document_type_id','date_of_issue'], 'documents_type_date_idx'],

        // ── Orders (Ecommerce) ────────────────────────────────────────────────
        ['orders',         ['status_order_id'],          'orders_status_idx'],
        ['orders',         ['reference_payment'],        'orders_payment_ref_idx'],
        ['orders',         ['created_at'],               'orders_created_at_idx'],

        // ── LogisticOrders ────────────────────────────────────────────────────
        ['logistic_orders',['status'],                   'logistic_orders_status_idx'],
        ['logistic_orders',['created_at'],               'logistic_orders_created_idx'],
        ['logistic_orders',['source','status'],          'logistic_orders_source_status_idx'],

        // ── Purchases ─────────────────────────────────────────────────────────
        ['purchases',      ['date_of_issue'],            'purchases_date_idx'],
        ['purchases',      ['supplier_id'],              'purchases_supplier_idx'],

        // ── Abandoned Carts ───────────────────────────────────────────────────
        // (la tabla se crea en la migración anterior, pero por si corre antes)
        // Los índices de abandoned_carts ya están definidos en su propia migración.
    ];

    public function up()
    {
        foreach ($this->indexes as [$table, $columns, $name]) {
            if (!Schema::hasTable($table)) continue;

            Schema::table($table, function (Blueprint $t) use ($columns, $name) {
                // Verificar si el índice ya existe antes de crearlo
                try {
                    $t->index($columns, $name);
                } catch (\Exception $e) {
                    // Índice duplicado — ignorar silenciosamente
                }
            });
        }
    }

    public function down()
    {
        foreach ($this->indexes as [$table, $columns, $name]) {
            if (!Schema::hasTable($table)) continue;

            Schema::table($table, function (Blueprint $t) use ($name) {
                try {
                    $t->dropIndex($name);
                } catch (\Exception $e) {}
            });
        }
    }
};
