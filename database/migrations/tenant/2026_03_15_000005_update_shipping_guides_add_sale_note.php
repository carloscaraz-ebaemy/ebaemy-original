<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('logistic_shipping_guides')) {
            return;
        }

        // Drop FK solo si existe
        try {
            Schema::table('logistic_shipping_guides', function (Blueprint $table) {
                $table->dropForeign(['logistic_order_id']);
            });
        } catch (\Throwable $e) {
            // La FK ya no existe o nunca se creó — ignorar
        }

        try {
            Schema::table('logistic_shipping_guides', function (Blueprint $table) {
                $table->unsignedBigInteger('logistic_order_id')->nullable()->change();
            });
        } catch (\Throwable $e) {
            // Ya es nullable — ignorar
        }

        if (!Schema::hasColumn('logistic_shipping_guides', 'sale_note_id')) {
            Schema::table('logistic_shipping_guides', function (Blueprint $table) {
                $table->unsignedBigInteger('sale_note_id')->nullable()->after('logistic_order_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('logistic_shipping_guides')) {
            return;
        }

        if (Schema::hasColumn('logistic_shipping_guides', 'sale_note_id')) {
            Schema::table('logistic_shipping_guides', function (Blueprint $table) {
                $table->dropColumn('sale_note_id');
            });
        }

        try {
            Schema::table('logistic_shipping_guides', function (Blueprint $table) {
                $table->unsignedBigInteger('logistic_order_id')->nullable(false)->change();
                $table->foreign('logistic_order_id')->references('id')->on('logistic_orders')->onDelete('cascade');
            });
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
