<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPreferredCarrierToSaleNotesTable extends Migration
{
    public function up()
    {
        Schema::table('sale_notes', function (Blueprint $table) {
            // ID del transportista registrado (Dispatcher) vinculado a la guía de remisión
            $table->unsignedBigInteger('preferred_carrier_id')->nullable()->after('preferred_courier');
        });
    }

    public function down()
    {
        Schema::table('sale_notes', function (Blueprint $table) {
            $table->dropColumn('preferred_carrier_id');
        });
    }
}
