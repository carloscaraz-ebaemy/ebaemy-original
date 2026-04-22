<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reviews cross-tenant en el marketplace central.
 *
 * Un review se asocia a un listing específico (NO al tenant global) para que
 * Google y el comprador puedan ver la calificación del producto vendido en
 * ese tenant. El promedio y conteo se denormalizan en marketplace_listings
 * para queries rápidas sin agregados.
 *
 * Moderación: por defecto pending_review; un admin del landlord aprueba.
 */
class CreateMarketplaceReviewsTable extends Migration
{
    public function up()
    {
        Schema::create('marketplace_reviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('listing_id')->index();
            $table->unsignedInteger('hostname_id')->nullable()->index()
                  ->comment('Cache denormalizado para stats del tenant');

            $table->string('customer_name', 120);
            $table->string('customer_email', 180)->nullable()->index();
            $table->unsignedTinyInteger('rating')->comment('1-5');
            $table->text('comment')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])
                  ->default('pending')->index();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason', 200)->nullable();

            // Anti-spam
            $table->string('source_ip', 45)->nullable();
            $table->string('source_ua', 250)->nullable();

            $table->timestamps();

            $table->foreign('listing_id')
                  ->references('id')->on('marketplace_listings')
                  ->onDelete('cascade');
        });

        Schema::table('marketplace_listings', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_listings', 'avg_rating')) {
                $table->decimal('avg_rating', 3, 2)->default(0)
                      ->after('lead_count')->index();
            }
            if (!Schema::hasColumn('marketplace_listings', 'rating_count')) {
                $table->unsignedInteger('rating_count')->default(0)
                      ->after('avg_rating');
            }
        });
    }

    public function down()
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->dropColumn(['avg_rating', 'rating_count']);
        });
        Schema::dropIfExists('marketplace_reviews');
    }
}
