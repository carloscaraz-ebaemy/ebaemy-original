<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de themes del sistema (BD sistema, no tenant).
 * El Super Admin gestiona los themes disponibles.
 * Cada empresa selecciona uno de estos themes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('themes')) {
            return;
        }

        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);                    // "Ropa", "Tecnología", etc.
            $table->string('slug', 60)->unique();           // "ropa", "tecnologia"
            $table->string('path', 100);                    // Carpeta: "ropa", "tecnologia"
            $table->string('css_template', 30)->nullable(); // Mapeo al CSS: "fashion", "tech"
            $table->text('description')->nullable();
            $table->string('preview_image')->nullable();    // Screenshot del theme
            $table->string('category', 30)->default('general'); // general, nicho
            $table->boolean('is_active')->default(true);
            $table->boolean('is_premium')->default(false);  // Para marketplace futuro
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Insertar themes base (usando updateOrInsert para evitar duplicados)
        $themes = [
            ['name' => 'Default',      'slug' => 'default',     'path' => 'default',     'css_template' => 'generic',  'description' => 'Theme genérico para cualquier tipo de negocio',                                  'category' => 'general', 'is_active' => true, 'is_premium' => false, 'sort_order' => 0],
            ['name' => 'Moda & Ropa',  'slug' => 'ropa',        'path' => 'ropa',        'css_template' => 'fashion',  'description' => 'Theme especializado en moda, ropa y accesorios. Diseño minimalista tipo Zara/Falabella.', 'category' => 'nicho', 'is_active' => true, 'is_premium' => false, 'sort_order' => 1],
            ['name' => 'Tecnología',   'slug' => 'tecnologia',  'path' => 'tecnologia',  'css_template' => 'tech',     'description' => 'Theme para tiendas de electrónica, computación y gadgets.',                       'category' => 'nicho',   'is_active' => true, 'is_premium' => false, 'sort_order' => 2],
            ['name' => 'Alimentos',    'slug' => 'alimentos',   'path' => 'alimentos',   'css_template' => 'food',     'description' => 'Theme para restaurantes, delivery de comida y productos alimenticios.',             'category' => 'nicho',   'is_active' => true, 'is_premium' => false, 'sort_order' => 3],
            ['name' => 'Deportes',     'slug' => 'deportes',    'path' => 'deportes',     'css_template' => 'sports',   'description' => 'Theme para tiendas deportivas y fitness.',                                        'category' => 'nicho',   'is_active' => true, 'is_premium' => false, 'sort_order' => 4],
            ['name' => 'Lujo',         'slug' => 'lujo',        'path' => 'lujo',         'css_template' => 'luxury',   'description' => 'Theme premium para marcas de lujo y joyería.',                                   'category' => 'nicho',   'is_active' => true, 'is_premium' => true,  'sort_order' => 5],
            ['name' => 'Farmacia',     'slug' => 'farmacia',    'path' => 'farmacia',     'css_template' => 'pharmacy', 'description' => 'Theme para farmacias y tiendas de salud.',                                        'category' => 'nicho',   'is_active' => true, 'is_premium' => false, 'sort_order' => 6],
            ['name' => 'Ferretería',   'slug' => 'ferreteria',  'path' => 'ferreteria',   'css_template' => 'hardware', 'description' => 'Theme para ferreterías y materiales de construcción.',                             'category' => 'nicho',   'is_active' => true, 'is_premium' => false, 'sort_order' => 7],
        ];

        foreach ($themes as $theme) {
            \DB::table('themes')->updateOrInsert(
                ['slug' => $theme['slug']],
                array_merge($theme, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};
