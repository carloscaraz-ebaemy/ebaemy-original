<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crea las tablas del data warehouse analítico.
 *
 * Estructura denormalizada — optimizada para reads analíticos, no para OLTP.
 * Los datos provienen del ETL (EtlSyncWarehouse command) que agrega desde
 * todas las BDs de tenants.
 *
 * Tablas:
 *   dw_daily_sales     — ventas diarias por tenant (principal KPI table)
 *   dw_tenant_items    — snapshot del catálogo de productos por tenant
 *   dw_tenant_metrics  — métricas generales del tenant (usuarios, docs emitidos, etc.)
 *   dw_etl_log         — log de cada corrida del ETL
 *
 * Para correr las migraciones del warehouse:
 *   php artisan migrate --database=warehouse --path=database/migrations/warehouse
 */
return new class extends Migration
{
    protected $connection = 'warehouse';

    public function up(): void
    {
        $conn = 'warehouse';

        // ── 1. Ventas diarias por tenant ──────────────────────────────────────
        if (!Schema::connection($conn)->hasTable('dw_daily_sales')) {
        Schema::connection($conn)->create('dw_daily_sales', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_uuid', 36)->index();  // hyn website uuid
            $table->date('sale_date')->index();
            $table->string('document_type', 10)->default('NV'); // NV, F, B, etc.
            $table->unsignedInteger('count')       ->default(0); // cantidad de comprobantes
            $table->decimal('gross_amount', 14, 2) ->default(0); // monto bruto
            $table->decimal('net_amount',   14, 2) ->default(0); // monto neto (sin anulados)
            $table->decimal('igv_amount',   14, 2) ->default(0);
            $table->unsignedInteger('items_sold')  ->default(0); // líneas de items
            $table->string('currency_code', 3)     ->default('PEN');
            $table->timestamp('etl_synced_at')->useCurrent(); // última sincronización
            $table->unique(['tenant_uuid', 'sale_date', 'document_type'], 'dw_daily_sales_unique');
        });
        }

        // ── 2. Catálogo de items por tenant (snapshot diario) ─────────────────
        if (!Schema::connection($conn)->hasTable('dw_tenant_items')) {
        Schema::connection($conn)->create('dw_tenant_items', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_uuid', 36)->index();
            $table->unsignedBigInteger('item_id');    // ID en el tenant
            $table->string('internal_id', 50)->nullable();
            $table->string('description', 250);
            $table->string('category', 100)->nullable();
            $table->decimal('sale_unit_price', 14, 2)->default(0);
            $table->boolean('apply_store')->default(false);   // está en ecommerce
            $table->boolean('has_variants')->default(false);
            $table->decimal('stock_physical', 14, 2)->default(0);  // suma de almacenes
            $table->unsignedInteger('sales_count_30d')->default(0); // ventas últimos 30d
            $table->date('snapshot_date'); // fecha del snapshot
            $table->timestamp('etl_synced_at')->useCurrent();
            $table->index(['tenant_uuid', 'snapshot_date']);
            $table->unique(['tenant_uuid', 'item_id', 'snapshot_date'], 'dw_items_unique');
        });
        }

        // ── 3. Métricas generales por tenant ──────────────────────────────────
        if (!Schema::connection($conn)->hasTable('dw_tenant_metrics')) {
        Schema::connection($conn)->create('dw_tenant_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_uuid', 36)->unique();
            $table->string('tenant_hostname', 150)->nullable();
            $table->string('plan_name', 100)->nullable();
            // Conteos acumulados
            $table->unsignedInteger('total_users')     ->default(0);
            $table->unsignedInteger('total_items')     ->default(0);
            $table->unsignedInteger('total_customers') ->default(0);
            $table->unsignedBigInteger('total_documents')->default(0);
            $table->unsignedBigInteger('total_sale_notes')->default(0);
            // Métricas del último mes
            $table->decimal('sales_last_30d', 14, 2)->default(0);
            $table->decimal('sales_last_12m', 14, 2)->default(0);
            $table->unsignedInteger('active_items_ecommerce')->default(0);
            // Flags de módulos activos
            $table->boolean('has_ecommerce')    ->default(false);
            $table->boolean('has_logistic')     ->default(false);
            $table->boolean('has_smart_stock')  ->default(false);
            $table->timestamp('last_sale_at')->nullable();
            $table->timestamp('etl_synced_at')->useCurrent();
        });
        }

        // ── 4. Log de corridas ETL ─────────────────────────────────────────────
        if (!Schema::connection($conn)->hasTable('dw_etl_log')) {
        Schema::connection($conn)->create('dw_etl_log', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_uuid', 36)->nullable()->index();  // null = corrida global
            $table->string('job_type', 50)->default('full');         // full | incremental | metrics
            $table->enum('status', ['running', 'success', 'failed'])->default('running');
            $table->unsignedInteger('rows_inserted')->default(0);
            $table->unsignedInteger('rows_updated') ->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->index('started_at');
        });
        }
    }

    public function down(): void
    {
        Schema::connection('warehouse')->dropIfExists('dw_etl_log');
        Schema::connection('warehouse')->dropIfExists('dw_tenant_metrics');
        Schema::connection('warehouse')->dropIfExists('dw_tenant_items');
        Schema::connection('warehouse')->dropIfExists('dw_daily_sales');
    }
};
