<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatsappOfferCampaignTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('whatsapp_offer_campaigns')) {
            Schema::create('whatsapp_offer_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('flash_sale_id')->nullable();
                $table->string('status', 20)->default('processing'); // processing|completed|failed
                $table->unsignedInteger('total_customers')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->json('meta')->nullable();
                $table->dateTime('started_at')->nullable();
                $table->dateTime('finished_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->foreign('flash_sale_id')->references('id')->on('flash_sales')->onDelete('set null');
            });
        }

        if (!Schema::hasTable('whatsapp_offer_campaign_messages')) {
            Schema::create('whatsapp_offer_campaign_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('campaign_id');
                $table->unsignedBigInteger('person_id');
                $table->string('phone', 30)->nullable();
                $table->string('status', 20)->default('pending'); // pending|sent|failed|skipped
                $table->json('payload')->nullable();
                $table->text('error_message')->nullable();
                $table->dateTime('sent_at')->nullable();
                $table->timestamps();

                $table->unique(['campaign_id', 'person_id']);
                $table->index(['person_id', 'status', 'sent_at']);
                $table->foreign('campaign_id')->references('id')->on('whatsapp_offer_campaigns')->onDelete('cascade');
                $table->foreign('person_id')->references('id')->on('persons')->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_offer_campaign_messages');
        Schema::dropIfExists('whatsapp_offer_campaigns');
    }
}

