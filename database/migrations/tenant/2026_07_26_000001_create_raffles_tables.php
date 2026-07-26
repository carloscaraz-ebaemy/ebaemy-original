<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de Sorteos integrado con Gestión de Pedidos.
 *
 * 3 tablas (BD del tenant, sin tenant_id — hyn es database-per-tenant):
 *   raffles              → la campaña (premio, vigencia, criterios de elegibilidad)
 *   raffle_participants  → cliente invitado + su token único + aceptación
 *   raffle_winners       → resultado del sorteo + entrega del premio
 *
 * Las FK a tablas legacy (persons, establishments, users, items) van como
 * unsignedInteger sin constraint — ver feedback_legacy_fk_types.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('raffles')) {
            Schema::create('raffles', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique();          // SRT-2026-0001
                $table->string('name', 160);
                $table->text('description')->nullable();
                $table->text('terms')->nullable();             // bases y condiciones

                // ── Premio ──────────────────────────────────────────────
                $table->string('prize_name', 160)->nullable();
                $table->text('prize_description')->nullable();
                $table->string('prize_image', 255)->nullable();   // filename del ImageProcessingService
                $table->json('prize_gallery')->nullable();        // [filename, ...]
                $table->unsignedSmallInteger('prize_quantity')->default(1);
                $table->decimal('prize_value', 10, 2)->nullable();

                // ── Vigencia ────────────────────────────────────────────
                $table->string('status', 20)->default('draft')->index(); // draft|active|finished|cancelled
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('registration_closes_at')->nullable();
                $table->dateTime('draw_at')->nullable();
                $table->dateTime('winner_published_at')->nullable();

                // ── Criterios de elegibilidad ───────────────────────────
                $table->json('sources')->nullable();              // ['documents','sale_notes','orders']
                $table->boolean('require_paid')->default(true);   // solo pagos confirmados
                $table->date('purchase_from')->nullable();
                $table->date('purchase_to')->nullable();
                $table->decimal('min_amount', 12, 2)->nullable(); // monto mínimo acumulado
                $table->unsignedInteger('establishment_id')->nullable();
                $table->unsignedInteger('channel_id')->nullable();
                $table->json('category_ids')->nullable();
                $table->json('item_ids')->nullable();

                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
                $table->index('draw_at');
            });
        }

        if (!Schema::hasTable('raffle_participants')) {
            Schema::create('raffle_participants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('raffle_id');
                $table->unsignedInteger('person_id')->nullable();

                // Identificación del cliente (congelada al generar la lista).
                $table->string('full_name', 200);
                $table->string('document', 20)->nullable();   // DNI / RUC
                $table->string('email', 160)->nullable();
                $table->string('phone', 30)->nullable();

                // Clave anti-duplicados: documento || email || telefono || person_id.
                $table->string('dedupe_key', 190);

                $table->string('token', 40)->unique();        // enlace /sorteo/{token}
                $table->string('status', 20)->default('invited'); // invited|accepted|declined

                // Trazabilidad de la compra que lo hizo elegible.
                $table->unsignedSmallInteger('orders_count')->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->date('last_purchase_at')->nullable();

                // Invitación.
                $table->dateTime('invited_at')->nullable();
                $table->string('invited_via', 20)->nullable(); // whatsapp|manual|link

                // Aceptación.
                $table->dateTime('accepted_at')->nullable();
                $table->string('accept_ip', 45)->nullable();
                $table->string('accept_user_agent', 255)->nullable();

                $table->boolean('is_winner')->default(false);
                $table->timestamps();

                $table->unique(['raffle_id', 'dedupe_key'], 'raffle_participants_unique_person');
                $table->index(['raffle_id', 'status']);
            });
        }

        if (!Schema::hasTable('raffle_winners')) {
            Schema::create('raffle_winners', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('raffle_id');
                $table->unsignedBigInteger('participant_id');
                $table->unsignedSmallInteger('position')->default(1);

                // Premio congelado al momento del sorteo.
                $table->string('prize_name', 160)->nullable();
                $table->string('prize_image', 255)->nullable();

                // Auditoría del sorteo.
                $table->dateTime('drawn_at')->nullable();
                $table->unsignedInteger('drawn_by')->nullable();
                $table->string('drawn_by_name', 120)->nullable();
                $table->json('draw_snapshot')->nullable();   // pool, seed, total participantes

                // Entrega.
                $table->string('delivery_status', 20)->default('pending'); // pending|delivered
                $table->dateTime('delivered_at')->nullable();
                $table->string('delivery_note', 255)->nullable();

                $table->timestamps();
                $table->index(['raffle_id', 'position']);
                $table->index('participant_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('raffle_winners');
        Schema::dropIfExists('raffle_participants');
        Schema::dropIfExists('raffles');
    }
};
