<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuration_ecommerce', function (Blueprint $table) {

            // Eliminar campos obsoletos
            $table->dropColumn([
                'seo_keywords',
                'tiktok_title',
                'tiktok_image',
            ]);

            // Agregar mejoras modernas
            $table->string('og_type', 50)->default('website')->after('og_image');
            $table->string('twitter_card', 50)->default('summary_large_image')->after('twitter_image');
            $table->longText('schema_json')->nullable()->after('indexable');
        });
    }

    public function down(): void
    {
        Schema::table('configuration_ecommerce', function (Blueprint $table) {

            $table->text('seo_keywords')->nullable();
            $table->string('tiktok_title', 255)->nullable();
            $table->string('tiktok_image', 255)->nullable();

            $table->dropColumn([
                'og_type',
                'twitter_card',
                'schema_json',
            ]);
        });
    }
};
