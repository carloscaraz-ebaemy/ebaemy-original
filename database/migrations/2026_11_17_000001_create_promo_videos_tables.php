<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Videos promocionales por producto, como extra de pago por tienda.
 *
 * Tres tablas en la base CENTRAL (no en la de cada tienda): quien aprueba es
 * el SuperAdmin, el marketplace los muestra y el proveedor de video es uno
 * solo para todos. Tenerlos aquí evita migrar 16 bases y recorrerlas para
 * armar la bandeja de aprobación.
 *
 * - `addons`: catálogo de extras que se venden aparte del plan, con su precio
 *   y cupo por defecto (editables en el SuperAdmin).
 * - `client_addons`: qué tienda tiene qué extra, a qué precio, con qué cupo y
 *   hasta cuándo está pagado. `FeatureGate` lo suma a los features del plan.
 * - `promo_videos`: UN video por producto (decidido 2026-11-17), de 5, 15 o
 *   30 segundos, que espera aprobación antes de publicarse.
 *
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('addons')) {
            Schema::create('addons', function (Blueprint $table) {
                $table->id();
                $table->string('key', 60)->unique();
                $table->string('name', 120);
                $table->string('description', 500)->nullable();
                $table->decimal('default_monthly_price', 10, 2)->default(0);
                // NULL = sin tope.
                $table->unsignedInteger('default_quota')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!DB::table('addons')->where('key', 'promo_videos')->exists()) {
            DB::table('addons')->insert([
                'key'                   => 'promo_videos',
                'name'                  => 'Videos promocionales',
                'description'           => 'Un video corto (5, 15 o 30 s) por producto, para la ficha, las páginas de ebaemy y los anuncios en Google, Meta y TikTok.',
                'default_monthly_price' => 0,
                'default_quota'         => 10,
                'is_active'             => true,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);
        }

        if (!Schema::hasTable('client_addons')) {
            Schema::create('client_addons', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('client_id');
                $table->string('addon_key', 60);
                // active: en uso · suspended: pausado a mano · cancelled: dado de baja.
                // Vencer sin pagar no cambia el estado: lo decide `paid_until`.
                $table->string('status', 20)->default('active');
                $table->decimal('monthly_price', 10, 2)->default(0);
                $table->unsignedInteger('quota')->nullable();
                $table->date('starts_at')->nullable();
                // Último día pagado. Pasado ese día el extra deja de valer y
                // los videos se OCULTAN, no se borran.
                $table->date('paid_until')->nullable();
                $table->string('notes', 500)->nullable();
                $table->unsignedInteger('enabled_by')->nullable();
                $table->timestamps();

                $table->unique(['client_id', 'addon_key'], 'client_addons_client_key_uniq');
                $table->index(['addon_key', 'status', 'paid_until'], 'client_addons_vigentes_idx');
            });
        }

        if (!Schema::hasTable('promo_videos')) {
            Schema::create('promo_videos', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('client_id');
                $table->unsignedInteger('hostname_id');
                $table->unsignedBigInteger('remote_item_id');
                $table->unsignedBigInteger('remote_variant_id')->nullable();
                // Copia del nombre al subirlo: la bandeja no consulta la tienda.
                $table->string('item_title', 250)->nullable();

                $table->unsignedTinyInteger('duration_seconds');      // 5, 15 o 30
                $table->string('aspect', 12)->default('vertical');     // vertical | square
                // uploading → processing → pending_review → approved | rejected
                // hidden: aprobado y retirado después · failed: el proveedor no pudo procesarlo
                $table->string('status', 20)->default('uploading');

                $table->string('provider', 30)->nullable();
                $table->string('provider_video_id', 120)->nullable();
                $table->string('playback_url', 500)->nullable();       // reproducción (HLS/embed)
                $table->string('mp4_url', 500)->nullable();            // descarga para anuncios
                $table->string('thumbnail_url', 500)->nullable();
                $table->unsignedSmallInteger('width')->nullable();
                $table->unsignedSmallInteger('height')->nullable();

                $table->string('title', 150)->nullable();
                $table->string('description', 500)->nullable();
                // Plantilla de promoción aplicada (precio, descuento, textos).
                $table->json('template')->nullable();

                $table->string('rejection_reason', 300)->nullable();
                $table->unsignedInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('published_at')->nullable();

                // Promoción en las páginas de ebaemy (portada del marketplace,
                // página de la tienda). Lo decide solo el SuperAdmin.
                $table->boolean('is_featured')->default(false);
                $table->date('featured_until')->nullable();

                $table->unsignedInteger('views')->default(0);
                $table->unsignedInteger('clicks')->default(0);
                $table->timestamps();

                $table->unique(['client_id', 'remote_item_id'], 'promo_videos_un_video_por_producto');
                $table->index(['status', 'created_at'], 'promo_videos_bandeja_idx');
                $table->index(['hostname_id', 'status'], 'promo_videos_tienda_idx');
                $table->index(['provider', 'provider_video_id'], 'promo_videos_proveedor_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_videos');
        Schema::dropIfExists('client_addons');
        Schema::dropIfExists('addons');
    }
};
