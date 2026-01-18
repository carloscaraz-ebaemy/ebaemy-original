<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnQrApiToConfigurations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('configurations', function (Blueprint $table)  {
            $msg = "Hola @variable_nombre \nEste es un recordatorio para informar que tiene un pago pendiente por sus servicios \nPlan *@variable_plan*\n- Monto: *@variable_precios*\n- Vence: *@variable_fecha_vencimiento*";
            $table->string('qr_api_url')->nullable()->default(null);
            $table->string('qr_api_token')->nullable()->default(null)->after('qr_api_url');
            $table->string('qr_api_msg')->default($msg)->after('qr_api_token');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn('qr_api_url');
            $table->dropColumn('qr_api_token');
            $table->dropColumn('qr_api_msg');
        });
    }
}
