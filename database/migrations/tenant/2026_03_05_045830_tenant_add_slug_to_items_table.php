<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * MIGRACIÓN SEO: Agregar campo slug a la tabla items
 * 
 * Para ejecutar:
 * php artisan migrate
 * 
 * Después de migrar, ejecutar el seeder para generar slugs:
 * php artisan db:seed --class=GenerateItemSlugsSeeder
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('slug', 600)->nullable()->after('description');
            $table->index('slug');
        });

        // Generar slugs para items existentes
        $items = \DB::table('items')->whereNull('slug')->orWhere('slug', '')->get();
        
        foreach ($items as $item) {
            $baseSlug = Str::slug($item->description ?? $item->name ?? 'producto-' . $item->id);
            
            // Asegurar que el slug sea único
            $slug = $baseSlug;
            $counter = 1;
            while (\DB::table('items')->where('slug', $slug)->where('id', '!=', $item->id)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
            
            \DB::table('items')->where('id', $item->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropColumn('slug');
        });
    }
};