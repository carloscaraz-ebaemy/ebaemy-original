<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddShippingDistrictToSaleNotesTable extends Migration
{
    public function up()
    {
        Schema::table('sale_notes', function (Blueprint $table) {
            $table->string('shipping_district_id', 10)->nullable()->after('shipping_city');
        });
    }

    public function down()
    {
        Schema::table('sale_notes', function (Blueprint $table) {
            $table->dropColumn('shipping_district_id');
        });
    }
}
