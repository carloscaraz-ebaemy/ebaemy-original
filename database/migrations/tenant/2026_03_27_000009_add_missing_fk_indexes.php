<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $indexes = [
            'sale_notes' => ['seller_id', 'warehouse_id', 'document_id', 'preferred_carrier_id'],
            'orders' => ['person_id', 'seller_id'],
            'dispatches' => ['reference_sale_note_id', 'reference_document_id', 'reference_order_note_id'],
            'users' => ['establishment_id', 'warehouse_id'],
            'inventory_kardex' => ['inventory_kardexable_id'],
            'logistic_orders' => ['user_id', 'shipping_guide_id'],
            'document_items' => ['document_id'],
            'sale_note_items' => ['sale_note_id'],
            'purchase_items' => ['purchase_id'],
        ];

        foreach ($indexes as $table => $columns) {
            if (!Schema::hasTable($table)) continue;
            Schema::table($table, function (Blueprint $t) use ($table, $columns) {
                foreach ($columns as $col) {
                    $idxName = "idx_{$table}_{$col}";
                    try {
                        if (Schema::hasColumn($table, $col) && !$this->hasIndex($table, $idxName)) {
                            $t->index($col, $idxName);
                        }
                    } catch (\Throwable $e) {
                        // Index may already exist under different name
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Indexes are safe to keep
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            return Schema::hasIndex($table, $indexName);
        } catch (\Throwable $e) {
            return false;
        }
    }
};
