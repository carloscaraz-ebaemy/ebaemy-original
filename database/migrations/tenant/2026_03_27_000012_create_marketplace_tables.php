<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Canales de marketplace conectados por tenant
        if (!Schema::hasTable('marketplace_channels')) {
            Schema::create('marketplace_channels', function (Blueprint $table) {
                $table->id();
                $table->string('platform', 30); // falabella, meta, mercadolibre
                $table->string('name', 100);
                $table->string('status', 20)->default('active'); // active, paused, error
                $table->json('credentials')->nullable(); // encrypted: api_key, user_id, token
                $table->json('settings')->nullable(); // sync interval, auto_accept_orders, etc.
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamp('last_error_at')->nullable();
                $table->text('last_error_message')->nullable();
                $table->timestamps();
                $table->index('platform');
                $table->index('status');
            });
        }

        // Mapeo de productos internos → marketplace
        if (!Schema::hasTable('marketplace_products')) {
            Schema::create('marketplace_products', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('channel_id');
                $table->unsignedBigInteger('item_id');
                $table->unsignedBigInteger('item_variant_id')->nullable();
                $table->string('external_sku', 100)->nullable(); // SKU en Falabella/Meta
                $table->string('external_id', 100)->nullable(); // ID en marketplace
                $table->string('sync_status', 20)->default('pending'); // pending, synced, error, excluded
                $table->json('external_data')->nullable(); // data del marketplace
                $table->text('last_error')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->foreign('channel_id')->references('id')->on('marketplace_channels')->onDelete('cascade');
                $table->unique(['channel_id', 'item_id', 'item_variant_id'], 'uniq_channel_item_variant');
                $table->index(['channel_id', 'sync_status']);
                $table->index('external_sku');
            });
        }

        // Órdenes recibidas de marketplaces
        if (!Schema::hasTable('marketplace_orders')) {
            Schema::create('marketplace_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('channel_id');
                $table->string('external_order_id', 100);
                $table->string('status', 30)->default('pending'); // pending, processing, shipped, delivered, cancelled
                $table->json('customer_data')->nullable();
                $table->json('items_data')->nullable();
                $table->json('shipping_data')->nullable();
                $table->decimal('total', 12, 2)->default(0);
                $table->string('currency', 5)->default('PEN');
                $table->unsignedBigInteger('order_id')->nullable(); // FK a orders locales
                $table->unsignedBigInteger('sale_note_id')->nullable(); // FK a sale_notes
                $table->timestamp('ordered_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->foreign('channel_id')->references('id')->on('marketplace_channels')->onDelete('cascade');
                $table->unique(['channel_id', 'external_order_id']);
                $table->index('status');
            });
        }

        // Log de sincronización (auditoría completa)
        if (!Schema::hasTable('marketplace_sync_logs')) {
            Schema::create('marketplace_sync_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('channel_id');
                $table->string('action', 50); // sync_products, sync_stock, fetch_orders, update_price
                $table->string('status', 20); // success, error, partial
                $table->string('direction', 10); // push, pull
                $table->integer('items_processed')->default(0);
                $table->integer('items_success')->default(0);
                $table->integer('items_failed')->default(0);
                $table->json('details')->nullable(); // errores detallados
                $table->integer('duration_ms')->nullable();
                $table->timestamps();

                $table->foreign('channel_id')->references('id')->on('marketplace_channels')->onDelete('cascade');
                $table->index(['channel_id', 'action', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_sync_logs');
        Schema::dropIfExists('marketplace_orders');
        Schema::dropIfExists('marketplace_products');
        Schema::dropIfExists('marketplace_channels');
    }
};
