<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('sale_notes', function (Blueprint $table) {
            $table->text('warehouse_notes')->nullable()->after('pickup_person')
                  ->comment('Observaciones internas del almacenero — no visibles al cliente');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('sale_notes', function (Blueprint $table) {
            $table->dropColumn('warehouse_notes');
        });
    }
};
