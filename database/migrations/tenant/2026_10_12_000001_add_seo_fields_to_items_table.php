<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Titulo y meta descripcion propios por producto.
 *
 * Hasta ahora la ficha derivaba su <title> del nombre del producto y su meta
 * description del texto comercial. Funciona, pero no deja afinar nada: ni una
 * sola de las fichas publicadas podia tener un titulo pensado para lo que la
 * gente teclea en Google.
 *
 * Las dos columnas son ANULABLES y sin valor por defecto a proposito: un
 * producto que no las use se comporta exactamente igual que antes, porque la
 * vista sigue cayendo en el nombre. No hay backfill ni dato inventado.
 *
 * Longitudes: Google recorta el titulo cerca de los 60 caracteres y la
 * descripcion cerca de los 155. Se deja algo de margen (70 y 180) porque el
 * corte real depende del ancho en pixeles, no del numero de caracteres, y
 * cortar en la base de datos seria peor que dejar que lo haga el buscador.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('items')) {
            return;
        }

        Schema::connection('tenant')->table('items', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('items', 'seo_title')) {
                $table->string('seo_title', 70)->nullable();
            }
            if (!Schema::connection('tenant')->hasColumn('items', 'seo_description')) {
                $table->string('seo_description', 180)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('items')) {
            return;
        }

        Schema::connection('tenant')->table('items', function (Blueprint $table) {
            foreach (['seo_title', 'seo_description'] as $columna) {
                if (Schema::connection('tenant')->hasColumn('items', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
