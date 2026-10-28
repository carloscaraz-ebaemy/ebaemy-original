<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ¿La oficina de Shalom atiende envíos aéreos? (`ter_aereo` del catálogo).
 * La llena shalom:sync-agencies; aquí se rellena desde `raw` para no esperar.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('shalom_agencies') && !Schema::hasColumn('shalom_agencies', 'air')) {
            Schema::table('shalom_agencies', function (Blueprint $t) {
                $t->boolean('air')->default(false)->after('home_delivery');
            });
            \Illuminate\Support\Facades\DB::table('shalom_agencies')
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(raw, '$.ter_aereo')) = '1'")
                ->update(['air' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shalom_agencies', 'air')) {
            Schema::table('shalom_agencies', fn (Blueprint $t) => $t->dropColumn('air'));
        }
    }
};
