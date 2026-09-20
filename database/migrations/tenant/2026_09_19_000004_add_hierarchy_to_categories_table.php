<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jerarquía en `categories`.
 *
 * La tabla nació plana en 2019 (`id`, `name`) y así seguía. Mientras el tenant
 * creaba sus categorías a mano no se notaba —seis o siete y a otra cosa—, pero
 * la importación de Saga crea una categoría del tenant por cada hoja de la
 * taxonomía de Falabella: carolayimport acabó con 164 categorías para 319
 * productos y 23 de ellas con un solo producto. Sin padre no hay forma de
 * agruparlas, y la tienda las pinta todas de golpe en la barra.
 *
 * Dos niveles, no más: padre → hija. Un árbol profundo no lo mantiene nadie y
 * el escaparate no sabría pintarlo.
 *
 * FK a tabla legacy con `unsignedInteger` y sin constraint: `categories.id` es
 * `increments`, y una FK autorreferente complicaría el borrado sin aportar
 * nada que el código no vigile ya.
 *
 * Idempotente (`hasColumn`): 17 tenants.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('categories')) {
            return;
        }

        if (!$schema->hasColumn('categories', 'parent_id')) {
            $schema->table('categories', function (Blueprint $table) {
                $table->unsignedInteger('parent_id')->nullable()->after('id');
                $table->index('parent_id');
            });
        }

        // Orden dentro del nivel. Los padres se ordenan entre sí y las hijas
        // dentro de su padre; 0 significa «sin colocar», y el listado cae
        // entonces al alfabético.
        if (!$schema->hasColumn('categories', 'sort_order')) {
            $schema->table('categories', function (Blueprint $table) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('name');
            });
        }

        // Una categoría puede existir para el ERP y no querer verse en la
        // tienda (las «Sin clasificar» que deja la importación, por ejemplo).
        if (!$schema->hasColumn('categories', 'visible_ecommerce')) {
            $schema->table('categories', function (Blueprint $table) {
                $table->boolean('visible_ecommerce')->default(true)->after('sort_order');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('categories')) {
            return;
        }

        foreach (['visible_ecommerce', 'sort_order', 'parent_id'] as $column) {
            if ($schema->hasColumn('categories', $column)) {
                $schema->table('categories', function (Blueprint $table) use ($column) {
                    if ($column === 'parent_id') {
                        $table->dropIndex(['parent_id']);
                    }
                    $table->dropColumn($column);
                });
            }
        }
    }
};
