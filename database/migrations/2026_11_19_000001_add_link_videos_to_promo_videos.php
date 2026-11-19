<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Videos de producto POR ENLACE (decidido 2026-11-19): la tienda pega el
 * enlace de un video ya publicado en YouTube (incluido Shorts), TikTok o
 * Instagram (Reels) y se muestra en el marketplace, sin servicio de video en
 * la nube. Misma tabla `promo_videos` de la fase 1, mismo flujo de
 * aprobación; cuando llegue la nube, los subidos conviven con estos.
 *
 * - `kind`: upload (archivo propio, fase 1) | link (enlace a un proveedor).
 * - En los de enlace, `provider` = youtube | tiktok | instagram y
 *   `provider_video_id` = el id extraído y validado de la URL. El iframe se
 *   arma SIEMPRE desde ese id (App\Services\Marketplace\VideoLink).
 * - `source_url`: la URL canónica (reconstruida, no la pegada tal cual).
 * - `source_title`: el título/descripción del oEmbed, solo para la bandeja.
 * - `aspect` admite ahora `horizontal` (YouTube normal) además de vertical.
 * - `duration_seconds` pasa a NULL: de un enlace no se conoce la duración.
 *
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('promo_videos')) {
            return;
        }

        Schema::table('promo_videos', function (Blueprint $table) {
            if (!Schema::hasColumn('promo_videos', 'kind')) {
                $table->string('kind', 10)->default('upload')->after('status');
            }
            if (!Schema::hasColumn('promo_videos', 'source_url')) {
                $table->string('source_url', 500)->nullable()->after('provider_video_id');
            }
            if (!Schema::hasColumn('promo_videos', 'source_title')) {
                $table->string('source_title', 300)->nullable()->after('source_url');
            }
        });

        $nullable = DB::selectOne(
            "SELECT IS_NULLABLE AS n FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'promo_videos' AND COLUMN_NAME = 'duration_seconds'"
        );
        if ($nullable && $nullable->n === 'NO') {
            DB::statement('ALTER TABLE promo_videos MODIFY duration_seconds TINYINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('promo_videos')) {
            return;
        }
        Schema::table('promo_videos', function (Blueprint $table) {
            foreach (['source_title', 'source_url', 'kind'] as $col) {
                if (Schema::hasColumn('promo_videos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
