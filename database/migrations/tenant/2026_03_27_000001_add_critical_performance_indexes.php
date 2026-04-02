<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Documents - most queried table
        if (Schema::hasTable('documents')) {
            $this->safeIndex('documents', ['person_id', 'date_of_issue'], 'idx_docs_person_date');
            $this->safeIndex('documents', ['state_type_id', 'date_of_issue'], 'idx_docs_state_date');
            $this->safeIndex('documents', ['user_id', 'date_of_issue'], 'idx_docs_user_date');
            $this->safeIndex('documents', ['document_type_id', 'series', 'number'], 'idx_docs_type_series_num');
        }

        // Sale Notes
        if (Schema::hasTable('sale_notes')) {
            $this->safeIndex('sale_notes', ['customer_id', 'date_of_issue'], 'idx_sn_customer_date');
            $this->safeIndex('sale_notes', ['state_type_id', 'date_of_issue'], 'idx_sn_state_date');
            $this->safeIndex('sale_notes', ['logistic_status', 'requires_warehouse_dispatch'], 'idx_sn_logistic');
            $this->safeIndex('sale_notes', ['warehouse_id', 'logistic_status'], 'idx_sn_warehouse_status');
        }

        // Orders
        if (Schema::hasTable('orders')) {
            $this->safeIndex('orders', ['status_order_id', 'created_at'], 'idx_orders_status_created');
            $this->safeIndex('orders', ['channel_id', 'created_at'], 'idx_orders_channel_created');
            $this->safeIndex('orders', ['person_id'], 'idx_orders_person');
        }

        // Item Warehouse - critical for stock queries
        if (Schema::hasTable('item_warehouse')) {
            try {
                Schema::table('item_warehouse', function (Blueprint $table) {
                    $table->unique(['item_id', 'warehouse_id'], 'idx_iw_item_warehouse');
                });
            } catch (\Throwable $e) {
                \Log::warning("Migration: could not add item_warehouse unique index: {$e->getMessage()}");
            }
        }

        // Stock Movements - audit trail queries
        if (Schema::hasTable('stock_movements')) {
            $this->safeIndex('stock_movements', ['item_id', 'warehouse_id', 'created_at'], 'idx_sm_item_wh_date');
            $this->safeIndex('stock_movements', ['type', 'created_at'], 'idx_sm_type_date');
            $this->safeIndex('stock_movements', ['reference_type', 'reference_id'], 'idx_sm_reference');
        }

        // Persons
        if (Schema::hasTable('persons')) {
            $this->safeIndex('persons', ['type', 'name'], 'idx_persons_type_name');
            $this->safeIndex('persons', ['number'], 'idx_persons_number');
        }

        // Inventory Kardex
        if (Schema::hasTable('inventory_kardex')) {
            $this->safeIndex('inventory_kardex', ['item_id', 'date_of_issue'], 'idx_kardex_item_date');
            $this->safeIndex('inventory_kardex', ['warehouse_id', 'date_of_issue'], 'idx_kardex_wh_date');
        }

        // Abandoned Carts
        if (Schema::hasTable('abandoned_carts')) {
            $this->safeIndex('abandoned_carts', ['recovered_at', 'expires_at'], 'idx_ac_status');
            $this->safeIndex('abandoned_carts', ['customer_email'], 'idx_ac_email');
        }
    }

    public function down(): void
    {
        $indexes = [
            'documents' => ['idx_docs_person_date', 'idx_docs_state_date', 'idx_docs_user_date', 'idx_docs_type_series_num'],
            'sale_notes' => ['idx_sn_customer_date', 'idx_sn_state_date', 'idx_sn_logistic', 'idx_sn_warehouse_status'],
            'orders' => ['idx_orders_status_created', 'idx_orders_channel_created', 'idx_orders_person'],
            'item_warehouse' => ['idx_iw_item_warehouse'],
            'stock_movements' => ['idx_sm_item_wh_date', 'idx_sm_type_date', 'idx_sm_reference'],
            'persons' => ['idx_persons_type_name', 'idx_persons_number'],
            'inventory_kardex' => ['idx_kardex_item_date', 'idx_kardex_wh_date'],
            'abandoned_carts' => ['idx_ac_status', 'idx_ac_email'],
        ];

        foreach ($indexes as $table => $tableIndexes) {
            if (Schema::hasTable($table)) {
                foreach ($tableIndexes as $idx) {
                    try {
                        Schema::table($table, function (Blueprint $t) use ($idx) {
                            $t->dropIndex($idx);
                        });
                    } catch (\Throwable $e) {
                    }
                }
            }
        }
    }

    /**
     * Add an index safely, catching duplicate index errors.
     */
    private function safeIndex(string $table, array $columns, string $name): void
    {
        try {
            Schema::table($table, function (Blueprint $t) use ($columns, $name) {
                $t->index($columns, $name);
            });
        } catch (\Throwable $e) {
            \Log::warning("Migration: could not add index {$name} on {$table}: {$e->getMessage()}");
        }
    }
};
