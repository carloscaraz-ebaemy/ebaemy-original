<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = [
            'sale_notes' => ['consigned_id', 'shipping_district_id', 'user_rel_suscription_plan_id', 'warehouse_user_id'],
            'documents' => ['consigned_id', 'user_rel_suscription_plan_id'],
            'dispatches' => ['delivery_address_id', 'origin_address_id', 'reference_order_form_id', 'reference_quotation_id', 'external_id'],
            'orders' => ['external_id', 'document_external_id'],
            'users' => ['identity_document_type_id', 'multi_user_id', 'series_id', 'zone_id', 'document_id'],
            'inventories' => ['guide_id', 'warehouse_destination_id'],
            'logistic_orders' => ['currency_type_id', 'warehouse_user_id'],
            'module_level_user' => ['module_level_id', 'user_id'],
            'module_user' => ['module_id', 'user_id'],
            'quotation_items' => ['quotation_id'],
            'dispatch_items' => ['dispatch_id'],
            'cash_documents' => ['cash_id'],
            'sale_note_payments' => ['sale_note_id'],
            'document_payments' => ['document_id'],
            'purchase_payments' => ['purchase_id'],
        ];

        foreach ($indexes as $table => $columns) {
            if (!Schema::hasTable($table)) continue;
            foreach ($columns as $col) {
                if (!Schema::hasColumn($table, $col)) continue;
                $idxName = "idx_{$table}_{$col}";
                try {
                    Schema::table($table, fn(Blueprint $t) => $t->index($col, $idxName));
                } catch (\Throwable $e) {
                    // Index may already exist
                    Log::debug("Index {$idxName} skipped: " . $e->getMessage());
                }
            }
        }
    }

    public function down(): void
    {
        // Indexes are safe to keep
    }
};
