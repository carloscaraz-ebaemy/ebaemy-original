<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing responsable — Fase 2.4.
 *
 * Crea esquema central para campañas multicanal (WhatsApp/Email/SMS) con
 * consentimiento explícito y opt-out trazable. Cumple con buenas prácticas:
 *   - Sin consent_marketing=true no se envía a un contacto
 *   - Cada envío tiene un opt-out token único para cancelar suscripción
 *   - Logs por target permiten auditar qué se envió a quién
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketing_contacts')) {
            Schema::create('marketing_contacts', function (Blueprint $table) {
                $table->id();
                $table->string('name', 180)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('email', 180)->nullable();

                $table->boolean('consent_marketing')->default(false)
                    ->comment('TRUE = aceptó recibir promociones; sin esto no se envía nada');
                $table->timestamp('consent_at')->nullable();
                $table->string('consent_source', 64)->nullable()
                    ->comment('signup_seller|checkout|import|landing|api');

                $table->boolean('opted_out')->default(false);
                $table->timestamp('opted_out_at')->nullable();
                $table->string('opt_out_reason', 200)->nullable();
                $table->string('opt_out_token', 64)->unique()
                    ->comment('Para que el link de cancelar suscripción funcione sin login');

                $table->json('tags')->nullable()
                    ->comment('etiquetas libres para segmentación: ["restaurante","abarrotes",...]');
                $table->unsignedBigInteger('hostname_id')->nullable()
                    ->comment('si el contacto vino de un tenant específico');

                $table->string('source', 64)->nullable();
                $table->timestamp('last_sent_at')->nullable();
                $table->unsignedInteger('sent_count')->default(0);

                $table->timestamps();

                $table->index('phone');
                $table->index('email');
                $table->index(['consent_marketing', 'opted_out']);
            });
        }

        if (!Schema::hasTable('marketing_campaigns')) {
            Schema::create('marketing_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('name', 180);
                $table->string('channel', 16)
                    ->comment('whatsapp|email|sms');
                $table->text('message');
                $table->string('subject', 200)->nullable()
                    ->comment('solo para email');

                $table->string('status', 32)->default('draft')
                    ->comment('draft|scheduled|sending|sent|cancelled');

                $table->json('segment')->nullable()
                    ->comment('reglas de segmentación: {tags, hostname_id, source}');
                $table->unsignedInteger('target_count')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);

                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();

                $table->unsignedBigInteger('created_by')->nullable();

                $table->timestamps();

                $table->index('status');
                $table->index('channel');
                $table->index('scheduled_at');
            });
        }

        if (!Schema::hasTable('marketing_campaign_targets')) {
            Schema::create('marketing_campaign_targets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('campaign_id');
                $table->unsignedBigInteger('contact_id');

                $table->string('status', 24)->default('pending')
                    ->comment('pending|sent|failed|skipped');
                $table->text('error')->nullable();
                $table->timestamp('sent_at')->nullable();

                $table->string('skip_reason', 64)->nullable()
                    ->comment('opted_out|no_consent|missing_channel|invalid');

                $table->timestamps();

                $table->foreign('campaign_id')->references('id')->on('marketing_campaigns')->onDelete('cascade');
                $table->foreign('contact_id')->references('id')->on('marketing_contacts')->onDelete('cascade');

                $table->unique(['campaign_id', 'contact_id']);
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaign_targets');
        Schema::dropIfExists('marketing_campaigns');
        Schema::dropIfExists('marketing_contacts');
    }
};
