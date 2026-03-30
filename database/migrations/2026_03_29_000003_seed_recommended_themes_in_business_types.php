<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Asignar recommended_theme_id a cada business_type según su theme correspondiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $map = [
            'ropa'       => 'ropa',
            'tecnologia' => 'tecnologia',
            'alimentos'  => 'alimentos',
            'deportes'   => 'deportes',
            'salud'      => 'farmacia',
            'ferreteria' => 'ferreteria',
        ];

        foreach ($map as $btName => $themeSlug) {
            $theme = DB::table('themes')->where('slug', $themeSlug)->first();
            if ($theme) {
                DB::table('business_types')
                    ->where('name', $btName)
                    ->update(['recommended_theme_id' => $theme->id]);
            }
        }
    }

    public function down(): void
    {
        DB::table('business_types')->update(['recommended_theme_id' => null]);
    }
};
