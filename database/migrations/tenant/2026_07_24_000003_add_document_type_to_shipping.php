<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de documento del destinatario (dni | ruc | ce | pasaporte | otro).
 * Permite registrar clientes extranjeros, que antes no encajaban en el campo
 * "DNI/RUC" (que asumía 8 u 11 dígitos numéricos).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('shipping_requests')
            && !Schema::connection('tenant')->hasColumn('shipping_requests', 'document_type')) {
            Schema::connection('tenant')->table('shipping_requests', function (Blueprint $table) {
                $table->string('document_type', 12)->nullable()->after('dni');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('tenant')->hasTable('shipping_requests')
            && Schema::connection('tenant')->hasColumn('shipping_requests', 'document_type')) {
            Schema::connection('tenant')->table('shipping_requests', function (Blueprint $table) {
                $table->dropColumn('document_type');
            });
        }
    }
};
