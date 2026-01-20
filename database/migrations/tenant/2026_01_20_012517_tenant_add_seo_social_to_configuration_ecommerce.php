<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('configuration_ecommerce', function (Blueprint $table) {

            // =========================
            // SEO GENERAL
            // =========================
            $table->string('seo_title', 255)->nullable();
            $table->text('seo_description')->nullable();
            $table->text('seo_keywords')->nullable();
            $table->string('seo_author', 150)->nullable();
            $table->string('seo_robots', 50)->default('index, follow');

            // =========================
            // OPEN GRAPH (Facebook / WhatsApp / Instagram)
            // =========================
            $table->string('og_title', 255)->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image', 255)->nullable();

            // =========================
            // TIKTOK
            // =========================
            $table->string('tiktok_title', 255)->nullable();
            $table->string('tiktok_image', 255)->nullable();

            // =========================
            // TWITTER (X)
            // =========================
            $table->string('twitter_title', 255)->nullable();
            $table->text('twitter_description')->nullable();
            $table->string('twitter_image', 255)->nullable();

            // =========================
            // CANONICAL + INDEXACIÓN
            // =========================
            $table->string('canonical_url', 255)->nullable();
            $table->boolean('indexable')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            $table->dropColumn([
                'seo_title',
                'seo_description',
                'seo_keywords',
                'seo_author',
                'seo_robots',
                'og_title',
                'og_description',
                'og_image',
                'tiktok_title',
                'tiktok_image',
                'twitter_title',
                'twitter_description',
                'twitter_image',
                'canonical_url',
                'indexable',
            ]);
        });
    }
};
