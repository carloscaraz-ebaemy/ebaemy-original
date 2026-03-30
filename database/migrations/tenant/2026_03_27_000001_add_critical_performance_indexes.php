<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Documents - most queried table
        try {
            if (!Schema::hasIndex('documents', 'idx_docs_person_date')) {
                Schema::table('documents', function (Blueprint $table) {
                    $table->index(['person_id', 'date_of_issue'], 'idx_docs_person_date');
                    $table->index(['state_type_id', 'date_of_issue'], 'idx_docs_state_date');
                    $table->index(['user_id', 'date_of_issue'], 'idx_docs_user_date');
                    $table->index(['document_type_id', 'series', 'number'], 'idx_docs_type_series_num');
                });
            }
        } catch (\Throwable $e) {
            \Log::warning("Migration: could not add documents indexes: {$e->getMessage()}");
        }

        // Sale Notes
        try {
            if (Schema::hasTable('sale_notes') && !Schema::hasIndex('sale_notes', 'idx_sn_customer_date')) {
                Schema::table('sale_notes', function (Blueprint $table) {
                    $table->index(['customer_id', 'date_of_issue'], 'idx_sn_customer_date');
                    $table->index(['state_type_id', 'date_of_issue'], 'idx_sn_state_date');
                    $table->index(['logistic_status', 'requires_warehouse_dispatch'], 'idx_sn_logistic');
                    $table->index(['warehouse_id', 'logistic_status'], 'idx_sn_warehouse_status');
                });
            }
        } catch (\Throwable $e) {
            \Log::warning("Migration: could not add sale_notes indexes: {$e->getMessage()}");
        }

        // Orders
        try {
            if (Schema::hasTable('orders') && !Schema::hasIndex('orders', 'idx_orders_status_created')) {
                Schema::table('orders', function (Blueprint $table) {
                    $table->index(['status_order_id', 'created_at'], 'idx_orders_status_created');
                    $table->index(['channel_id', 'created_at'], 'idx_orders_channel_created');
                    $table->index(['person_id'], 'idx_orders_person');
                });
            }
        } catch (\Throwable $e) {
            \Log::warning("Migration: could not add orders indexes: {$e->getMessage()}");
        }

        // Item Warehouse - critical for stock queries
        try {
            if (Schema::hasTable('item_warehouse') && !Schema::hasIndex('item_warehouse', 'idx_iw_item_warehouse')) {
                Schema::table('item_warehouse', function (Blueprint $table) {
                    $table->unique(['item_id', 'warehouse_id'], 'idx_iw_item_warehouse');
                });
            }
        } catch (\Throwable $e) {
            \Log::warning("Migration: could not add item_warehouse indexes: {$e->getMessage()}");
        }

        // Stock Movements - audit trail queries
        try {
            if (Schema::hasTable('stock_movements') && !Schema::hasIndex('stock_movements', 'idx_sm_item_wh_date')) {
                Schema::table('stock_movements', function (Blueprint $table) {
                    $table->index(['item_id', 'warehouse_id', 'created_at'], 'idx_sm_item_wh_date');
                    $table->index(['type', 'created_at'], 'idx_sm_type_date');
                    $table->index(['reference_type', 'reference_id'], 'idx_sm_reference');
                });
            }
        } catch (\Throwable $e) {
            \Log::warning("Migration: could not add stock_movements indexes: {$e->getMessage()}");
        }

        // Persons
        try {
            if (!Schema::hasIndex('persons', 'idx_persons_type_name')) {
                Schema::table('persons', function (Blueprint $table) {
                    $table->index(['type', 'name'], 'idx_persons_type_name');
                    $table->index(['number'], 'idx_persons_number');
                });
            }
        } catch (\Throwable $e) {
            \Log::warning("Migration: could not add persons indexes: {$e->getMessage()}");
        }

        // Inventory Kardex
        try {
            if (Schema::hasTable('inventory_kardex') && !Schema::hasIndex('inventory_kardex', 'idx_kardex_item_date')) {
                Schema::table('inventory_kardex', function (Blueprint $table) {
                    $table->index(['item_id', 'date_of_issue'], 'idx_kardex_item_date');
                    $table->index(['warehouse_id', 'date_of_issue'], 'idx_kardex_wh_date');
                });
            }
        } catch (\Throwable $e) {
            \Log::warning("Migration: could not add inventory_kardex indexes: {$e->getMessage()}");
        }

        // Abandoned Carts
        try {
            if (Schema::hasTable('abandoned_carts') && !Schema::hasIndex('abandoned_carts', 'idx_ac_status')) {
                Schema::table('abandoned_carts', function (Blueprint $table) {
                    $table->index(['recovered_at', 'expires_at'], 'idx_ac_status');
                    $table->index(['customer_email'], 'idx_ac_email');
                });
            }
        } catch (\Throwable $e) {
            \Log::warning("Migration: could not add abandoned_carts indexes: {$e->getMessage()}");
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
                Schema::table($table, function (Blueprint $t) use ($tableIndexes) {
                    foreach ($tableIndexes as $idx) {
                        try {
                            $t->dropIndex($idx);
                        } catch (\Throwable $e) {
                        }
                    }
                });
            }
        }
    }
};
