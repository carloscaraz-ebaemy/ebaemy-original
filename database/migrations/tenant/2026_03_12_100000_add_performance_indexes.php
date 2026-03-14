<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índices compuestos para optimizar queries de reportes y búsquedas frecuentes.
 */
class AddPerformanceIndexes extends Migration
{
    /** Verifica si un índice existe antes de crearlo */
    private function hasIndex(string $table, string $indexName): bool
    {
        $result = DB::select(
            "SELECT COUNT(*) as cnt FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = ?
             AND INDEX_NAME = ?",
            [$table, $indexName]
        );
        return (int)($result[0]->cnt ?? 0) > 0;
    }

    private function addIndex(string $table, array $columns, string $name): void
    {
        if ($this->hasIndex($table, $name)) return;

        Schema::table($table, function (Blueprint $t) use ($columns, $name) {
            $t->index($columns, $name);
        });
    }

    private function dropIndex(string $table, string $name): void
    {
        if (!$this->hasIndex($table, $name)) return;

        Schema::table($table, function (Blueprint $t) use ($name) {
            $t->dropIndex($name);
        });
    }

    public function up()
    {
        // ── documents ──────────────────────────────────────────────────────
        // Reportes por establecimiento + rango de fechas (query más frecuente)
        $this->addIndex('documents', ['establishment_id', 'date_of_issue'], 'idx_documents_estab_date');

        // Resúmenes SUNAT: tipo + estado + fecha
        $this->addIndex('documents', ['document_type_id', 'state_type_id', 'date_of_issue'], 'idx_documents_type_state_date');

        // Historial de cliente
        $this->addIndex('documents', ['customer_id', 'date_of_issue'], 'idx_documents_customer_date');

        // ── sale_notes ─────────────────────────────────────────────────────
        // sale_notes no tiene ningún índice en date_of_issue
        $this->addIndex('sale_notes', ['date_of_issue'], 'idx_sale_notes_date');

        // Reportes por establecimiento + fecha
        $this->addIndex('sale_notes', ['establishment_id', 'date_of_issue'], 'idx_sale_notes_estab_date');

        // Reportes de vendedor
        $this->addIndex('sale_notes', ['user_id', 'date_of_issue'], 'idx_sale_notes_user_date');

        // Filtro por estado + fecha (notas pendientes de convertir a CPE)
        $this->addIndex('sale_notes', ['state_type_id', 'date_of_issue'], 'idx_sale_notes_state_date');

        // ── kardex ─────────────────────────────────────────────────────────
        // kardex_item_date_idx ya existe (item_id, date_of_issue)
        // Agrega type para filtrar entradas vs salidas por producto y fecha
        $this->addIndex('kardex', ['type', 'item_id', 'date_of_issue'], 'idx_kardex_type_item_date');

        // ── cash_documents ─────────────────────────────────────────────────
        // Acelera getTotalsIncomeSummary: busca todos los docs de una caja
        $this->addIndex('cash_documents', ['cash_id', 'sale_note_id'], 'idx_cash_docs_cash_salenote');
        $this->addIndex('cash_documents', ['cash_id', 'document_id'],  'idx_cash_docs_cash_document');
    }

    public function down()
    {
        $this->dropIndex('documents', 'idx_documents_estab_date');
        $this->dropIndex('documents', 'idx_documents_type_state_date');
        $this->dropIndex('documents', 'idx_documents_customer_date');

        $this->dropIndex('sale_notes', 'idx_sale_notes_date');
        $this->dropIndex('sale_notes', 'idx_sale_notes_estab_date');
        $this->dropIndex('sale_notes', 'idx_sale_notes_user_date');
        $this->dropIndex('sale_notes', 'idx_sale_notes_state_date');

        $this->dropIndex('kardex', 'idx_kardex_type_item_date');

        $this->dropIndex('cash_documents', 'idx_cash_docs_cash_salenote');
        $this->dropIndex('cash_documents', 'idx_cash_docs_cash_document');
    }
}
