<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        if (!Schema::hasTable('cat_unit_types')) {
            return;
        }

        DB::table('cat_unit_types')->where('id', 'TM')->delete();

        if (!Schema::hasColumn('cat_unit_types', 'symbol')) {
            Schema::table('cat_unit_types', function (Blueprint $table) {
                $table->string('symbol', 10)->nullable()->after('description');
            });
        }

        if (!Schema::hasColumn('cat_unit_types', 'active')) {
            Schema::table('cat_unit_types', function (Blueprint $table) {
                $table->boolean('active')->default(true)->after('symbol');
            });
        }

        $activeUnits = [
            'NIU',
            'ZZ',
            'KGM',
            'LTR',
            'MTR',
            'PK',
            'BX',
            'BG',
        ];

        $units = [
            // Unidades existentes
            ['id' => 'ZZ', 'description' => 'Servicio', 'symbol' => 'SERV'],
            ['id' => 'BX', 'description' => 'Caja', 'symbol' => 'CAJ'],
            ['id' => 'GLL', 'description' => 'Galones', 'symbol' => 'GL'],
            ['id' => 'GRM', 'description' => 'Gramos', 'symbol' => 'GR'],
            ['id' => 'KGM', 'description' => 'Kilos', 'symbol' => 'KG'],
            ['id' => 'LTR', 'description' => 'Litros', 'symbol' => 'LT'],
            ['id' => 'MTR', 'description' => 'Metros', 'symbol' => 'M'],
            ['id' => 'FOT', 'description' => 'Pies', 'symbol' => 'PIE'],
            ['id' => 'INH', 'description' => 'Pulgadas', 'symbol' => 'INCH'],
            ['id' => 'NIU', 'description' => 'Unidades', 'symbol' => 'UND'],
            ['id' => 'YRD', 'description' => 'Yardas', 'symbol' => 'YD'],
            ['id' => 'HUR', 'description' => 'Hora', 'symbol' => 'HR'],
            
            // Toneladas - solo TNE (TM eliminada)
            ['id' => 'TNE', 'description' => 'Toneladas', 'symbol' => 'TNL'],
            
            // Nuevas unidades del listado completo
            ['id' => 'DZN', 'description' => 'Docena', 'symbol' => 'DOC'],
            ['id' => 'QD', 'description' => 'Cuarto de docena', 'symbol' => '1/4 DOC'],
            ['id' => 'PK', 'description' => 'Paquete', 'symbol' => 'PQT'],
            ['id' => 'MTQ', 'description' => 'Metro cúbico', 'symbol' => 'M3'],
            ['id' => 'HD', 'description' => 'Media docena', 'symbol' => '1/2 DOC'],
            ['id' => 'PR', 'description' => 'Par', 'symbol' => 'PAR'],
            ['id' => 'JG', 'description' => 'Jarra', 'symbol' => 'JARR'],
            ['id' => 'JR', 'description' => 'Frasco', 'symbol' => 'FCO'],
            ['id' => 'KT', 'description' => 'Kit', 'symbol' => 'KIT'],
            ['id' => 'CH', 'description' => 'Envase', 'symbol' => 'ENV'],
            ['id' => 'AV', 'description' => 'Cápsula', 'symbol' => 'CAPS'],
            ['id' => 'CT', 'description' => 'Cartón', 'symbol' => 'CTON'],
            ['id' => 'CY', 'description' => 'Cilindro', 'symbol' => 'CIL'],
            ['id' => 'BE', 'description' => 'Fardo', 'symbol' => 'FARD'],
            ['id' => 'BG', 'description' => 'Bolsa', 'symbol' => 'BOLS'],
            ['id' => 'BJ', 'description' => 'Balde', 'symbol' => 'BALD'],
            ['id' => 'SET', 'description' => 'Juego', 'symbol' => 'JGO'],
            ['id' => 'BLL', 'description' => 'Barril', 'symbol' => 'BRL'],
            ['id' => 'RM', 'description' => 'Resma', 'symbol' => 'RESM'],
            ['id' => 'BO', 'description' => 'Botellas', 'symbol' => 'BOT'],
            ['id' => 'SA', 'description' => 'Saco', 'symbol' => 'SCO'],
            ['id' => 'BT', 'description' => 'Tornillo', 'symbol' => 'TORN'],
            ['id' => 'C62', 'description' => 'Piezas', 'symbol' => 'PZ'],
            ['id' => 'U2', 'description' => 'Tableta o blister', 'symbol' => 'BLIST'],
            ['id' => 'CA', 'description' => 'Latas', 'symbol' => 'LT'],
            ['id' => 'CEN', 'description' => 'Centenar o ciento', 'symbol' => 'CTO'],
            ['id' => 'CMT', 'description' => 'Centímetro', 'symbol' => 'CM'],
            ['id' => 'CMK', 'description' => 'Centímetro cuadrado', 'symbol' => 'CM2'],
            ['id' => 'CMQ', 'description' => 'Centímetro cúbico', 'symbol' => 'CM3'],
            ['id' => 'DZP', 'description' => 'Docena de paquetes', 'symbol' => 'DOC2'],
            ['id' => 'FTK', 'description' => 'Pies cuadrados', 'symbol' => 'PIE2'],
            ['id' => 'FTQ', 'description' => 'Pies cúbicos', 'symbol' => 'PIE3'],
            ['id' => 'GLI', 'description' => 'Galón inglés', 'symbol' => 'GL'],
            ['id' => 'HT', 'description' => 'Media hora', 'symbol' => '1/2 H'],
            ['id' => 'KTM', 'description' => 'Kilómetro', 'symbol' => 'KM'],
            ['id' => 'KWH', 'description' => 'Kilovatio hora', 'symbol' => 'KWxH'],
            ['id' => 'MWH', 'description' => 'Megavatio hora', 'symbol' => 'MWxH'],
            ['id' => 'LBR', 'description' => 'Libras', 'symbol' => 'LB'],
            ['id' => 'LEF', 'description' => 'Hoja', 'symbol' => 'HOJA'],
            ['id' => 'MGM', 'description' => 'Miligramos', 'symbol' => 'MG'],
            ['id' => 'MIL', 'description' => 'Millar', 'symbol' => 'MIL'],
            ['id' => 'MLT', 'description' => 'Mililitro', 'symbol' => 'ML'],
            ['id' => 'MMT', 'description' => 'Milímetro', 'symbol' => 'MM'],
            ['id' => 'MMK', 'description' => 'Milímetro cuadrado', 'symbol' => 'MM2'],
            ['id' => 'MMQ', 'description' => 'Milímetro cúbico', 'symbol' => 'MM3'],
            ['id' => 'MTK', 'description' => 'Metro cuadrado', 'symbol' => 'M2'],
            ['id' => 'ONZ', 'description' => 'Onzas', 'symbol' => 'ONZ'],
            ['id' => 'PF', 'description' => 'Paletas', 'symbol' => 'PAL'],
            ['id' => 'PG', 'description' => 'Placas', 'symbol' => 'PLAC'],
            ['id' => 'RD', 'description' => 'Varilla', 'symbol' => 'VAR'],
            ['id' => 'RL', 'description' => 'Carrete', 'symbol' => 'CRR'],
            ['id' => 'SEC', 'description' => 'Segundo', 'symbol' => 'SEG'],
            ['id' => 'ST', 'description' => 'Pliego', 'symbol' => 'PLGO'],
            ['id' => 'TU', 'description' => 'Tubos', 'symbol' => 'TB'],
            ['id' => 'UM', 'description' => 'Millón', 'symbol' => 'MILL'],
        ];

        foreach ($units as $unit) {
            $active = in_array($unit['id'], $activeUnits, true);
            
            DB::table('cat_unit_types')->updateOrInsert(
                ['id' => $unit['id']],
                [
                    'description' => $unit['description'],
                    'symbol' => $unit['symbol'],
                    'active' => $active,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {    
        if (!Schema::hasTable('cat_unit_types')) {
            return;
        }

        if (Schema::hasColumn('cat_unit_types', 'symbol')) {
            Schema::table('cat_unit_types', function (Blueprint $table) {
                $table->dropColumn('symbol');
            });
        }
        
        if (Schema::hasColumn('cat_unit_types', 'active')) {
            Schema::table('cat_unit_types', function (Blueprint $table) {
                $table->dropColumn('active');
            });
        }        
    }
};
