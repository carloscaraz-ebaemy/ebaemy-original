<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking de la boleta electrónica de cada pedido de marketplace (Saga):
 *   - document_id: Document (CPE) emitido y enlazado (FK lógica a documents).
 *   - invoice_uploaded_at: cuándo se subió el comprobante a Saga (SetInvoicePDF).
 *   - invoice_upload_error: último error de la carga a Saga (para reintento).
 *
 * FK a tabla legacy `documents` → unsignedInteger (no foreignId). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_orders')) {
            return;
        }
        Schema::table('marketplace_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_orders', 'document_id')) {
                $table->unsignedInteger('document_id')->nullable()->after('order_id');
            }
            if (!Schema::hasColumn('marketplace_orders', 'invoice_uploaded_at')) {
                $table->timestamp('invoice_uploaded_at')->nullable()->after('processed_at');
            }
            if (!Schema::hasColumn('marketplace_orders', 'invoice_upload_error')) {
                $table->text('invoice_upload_error')->nullable()->after('invoice_uploaded_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketplace_orders')) {
            return;
        }
        Schema::table('marketplace_orders', function (Blueprint $table) {
            foreach (['document_id', 'invoice_uploaded_at', 'invoice_upload_error'] as $col) {
                if (Schema::hasColumn('marketplace_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
