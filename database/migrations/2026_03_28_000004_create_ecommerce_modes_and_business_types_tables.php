<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas de modos de ecommerce y tipos de negocio (BD sistema).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Modos de ecommerce
        if (!Schema::hasTable('ecommerce_modes')) {
            Schema::create('ecommerce_modes', function (Blueprint $table) {
                $table->id();
                $table->string('name', 30)->unique();       // general, nicho
                $table->string('label', 60);                // "Marketplace General"
                $table->text('description')->nullable();
                $table->json('default_features')->nullable(); // Features habilitadas por defecto
                $table->json('default_settings')->nullable(); // Settings por defecto
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            \DB::table('ecommerce_modes')->updateOrInsert(
                ['name' => 'general'],
                ['label' => 'Marketplace General', 'description' => 'Tienda multi-categoría para cualquier tipo de producto', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
            \DB::table('ecommerce_modes')->updateOrInsert(
                ['name' => 'nicho'],
                ['label' => 'Tienda Especializada', 'description' => 'Tienda vertical optimizada para un rubro específico', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // Tipos de negocio (rubros)
        if (!Schema::hasTable('business_types')) {
            Schema::create('business_types', function (Blueprint $table) {
                $table->id();
                $table->string('name', 30)->unique();          // ropa, tecnologia
                $table->string('label', 60);                   // "Moda & Ropa"
                $table->text('description')->nullable();
                $table->unsignedBigInteger('recommended_theme_id')->nullable();
                $table->json('suggested_categories')->nullable(); // Categorías sugeridas al crear
                $table->json('required_fields')->nullable();      // Campos extra requeridos
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });

            $businessTypes = [
                ['name' => 'ropa',        'label' => 'Moda & Ropa',       'description' => 'Ropa, calzado y accesorios de moda',              'is_active' => true, 'sort_order' => 1],
                ['name' => 'tecnologia',  'label' => 'Tecnología',        'description' => 'Electrónica, computación y gadgets',               'is_active' => true, 'sort_order' => 2],
                ['name' => 'alimentos',   'label' => 'Alimentos',         'description' => 'Restaurantes, delivery y productos alimenticios',  'is_active' => true, 'sort_order' => 3],
                ['name' => 'deportes',    'label' => 'Deportes',          'description' => 'Artículos deportivos y fitness',                   'is_active' => true, 'sort_order' => 4],
                ['name' => 'salud',       'label' => 'Salud & Farmacia',  'description' => 'Farmacias, productos de salud y bienestar',       'is_active' => true, 'sort_order' => 5],
                ['name' => 'servicios',   'label' => 'Servicios',         'description' => 'Servicios profesionales y consultoría',            'is_active' => true, 'sort_order' => 6],
                ['name' => 'educacion',   'label' => 'Educación',         'description' => 'Cursos, materiales educativos y formación',        'is_active' => true, 'sort_order' => 7],
                ['name' => 'ferreteria',  'label' => 'Ferretería',        'description' => 'Materiales de construcción y herramientas',        'is_active' => true, 'sort_order' => 8],
            ];

            foreach ($businessTypes as $bt) {
                \DB::table('business_types')->updateOrInsert(
                    ['name' => $bt['name']],
                    array_merge($bt, ['created_at' => now(), 'updated_at' => now()])
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_types');
        Schema::dropIfExists('ecommerce_modes');
    }
};
