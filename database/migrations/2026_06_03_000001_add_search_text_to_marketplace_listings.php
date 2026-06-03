<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Búsqueda del marketplace insensible a tildes/mayúsculas.
 *
 * Agrega `search_text` (texto normalizado: minúsculas + sin acentos) a
 * marketplace_listings. Lo puebla MarketplaceListingSyncService::normalizeForSearch
 * en cada sync, y lo consulta MarketplaceListing::scopeSearch como índice primario
 * (con fallback a title/brand/category para filas aún no resincronizadas).
 *
 * BD central (conexión `system`). Idempotente.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('marketplace_listings', 'search_text')) {
            Schema::table('marketplace_listings', function (Blueprint $table) {
                $table->string('search_text', 500)->nullable()->after('brand_name')
                      ->comment('Texto normalizado (sin acentos) para búsqueda insensible a tildes');
            });
        }

        // Índice normal: ayuda a igualdades/prefijos; LIKE '%x%' hace scan acotado
        // sobre listings publicados. (Para typo-tolerance/velocidad: migrar a Scout
        // + Meilisearch como siguiente paso.)
        try {
            Schema::table('marketplace_listings', function (Blueprint $table) {
                $table->index('search_text', 'mpl_search_text_idx');
            });
        } catch (\Throwable $e) {
            // índice ya existe — ignorar
        }

        // Backfill de filas existentes: lower + strip de acentos españoles comunes.
        // (El próximo sync lo reescribe con la normalización canónica del servicio.)
        try {
            $expr = "LOWER(CONCAT_WS(' ', COALESCE(title,''), COALESCE(brand_name,''), COALESCE(category_name,'')))";
            $accents = [
                'á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a',
                'é'=>'e','è'=>'e','ë'=>'e','ê'=>'e',
                'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i',
                'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','õ'=>'o',
                'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u',
                'ñ'=>'n','ç'=>'c',
            ];
            foreach ($accents as $from => $to) {
                $expr = "REPLACE($expr, '$from', '$to')";
            }
            DB::statement("UPDATE marketplace_listings SET search_text = $expr WHERE search_text IS NULL OR search_text = ''");
        } catch (\Throwable $e) {
            // backfill best-effort; el sync repuebla igualmente
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('marketplace_listings', 'search_text')) {
            Schema::table('marketplace_listings', function (Blueprint $table) {
                try { $table->dropIndex('mpl_search_text_idx'); } catch (\Throwable $e) {}
                $table->dropColumn('search_text');
            });
        }
    }
};
