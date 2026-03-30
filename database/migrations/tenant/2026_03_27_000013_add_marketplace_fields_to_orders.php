<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) return;

        // Agregar campos para pedidos de marketplace (si no existen)
        $columns = ['external_order_ref', 'marketplace_notes'];

        Schema::table('orders', function (Blueprint $table) use ($columns) {
            if (!Schema::hasColumn('orders', 'external_order_ref')) {
                $table->string('external_order_ref', 100)->nullable()
                    ->after('channel_id')
                    ->comment('Nro pedido en Saga/ML/Instagram');
            }
            if (!Schema::hasColumn('orders', 'marketplace_notes')) {
                $table->text('marketplace_notes')->nullable()
                    ->after('external_order_ref')
                    ->comment('Notas del marketplace (link, captura, etc)');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) return;
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'external_order_ref')) $table->dropColumn('external_order_ref');
            if (Schema::hasColumn('orders', 'marketplace_notes')) $table->dropColumn('marketplace_notes');
        });
    }
};
