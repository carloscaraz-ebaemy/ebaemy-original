<?php

use App\Services\Tenant\ImageProcessingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * thumb_image_url — version de 512px (`_medium`) de la imagen de la card.
 *
 * Las cards del marketplace servian la variante `_mp` (1080x1080, q82) para
 * pintar un recuadro de ~260px. Son 24 cards por pagina: entre 4 y 6 MB de
 * imagen por carga de la home, casi todo descartado por el navegador al
 * reescalar. Con `_medium` (512px) la misma pagina baja a ~1,3 MB.
 *
 * Se guarda la URL ya resuelta en lugar de derivarla en el Blade porque la
 * variante puede NO existir (productos anteriores al pipeline de imagenes, o
 * los que quedaron con nomenclatura de guion `-medium`). Si no existe el
 * archivo la columna queda NULL y la card cae a `image_url` como hasta ahora,
 * de modo que no hay riesgo de imagen rota. Ver project_imagen_estandar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('system')->hasTable('marketplace_listings')) return;

        if (!Schema::connection('system')->hasColumn('marketplace_listings', 'thumb_image_url')) {
            Schema::connection('system')->table('marketplace_listings', function (Blueprint $table) {
                $table->string('thumb_image_url', 500)->nullable()->after('image_url');
            });
        }

        $this->backfill();
    }

    public function down(): void
    {
        if (Schema::connection('system')->hasColumn('marketplace_listings', 'thumb_image_url')) {
            Schema::connection('system')->table('marketplace_listings', function (Blueprint $table) {
                $table->dropColumn('thumb_image_url');
            });
        }
    }

    /**
     * Rellena la columna sin tener que re-sincronizar los 17 tenants: la URL
     * del listing ya apunta a `<fqdn>/storage/uploads/items/<archivo>_mp.<ext>`
     * y el disco de imagenes es plano y compartido por todos los tenants, asi
     * que basta comprobar el archivo y reescribir el sufijo.
     */
    private function backfill(): void
    {
        $disk = Storage::disk(ImageProcessingService::disk());

        DB::connection('system')->table('marketplace_listings')
            ->select('id', 'image_url')
            ->whereNotNull('image_url')
            ->where('image_url', '<>', '')
            ->whereNull('thumb_image_url')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($disk) {
                foreach ($rows as $row) {
                    $thumb = $this->resolveThumbUrl($disk, (string) $row->image_url);
                    if ($thumb === null) continue;

                    DB::connection('system')->table('marketplace_listings')
                        ->where('id', $row->id)
                        ->update(['thumb_image_url' => $thumb]);
                }
            });
    }

    /** @return string|null la URL de la miniatura, o null si no hay archivo */
    private function resolveThumbUrl($disk, string $imageUrl): ?string
    {
        $file = basename(parse_url($imageUrl, PHP_URL_PATH) ?: '');
        if ($file === '') return null;

        // El archivo base: le quitamos el sufijo `_mp` si lo trae.
        $ext  = pathinfo($file, PATHINFO_EXTENSION);
        $base = pathinfo($file, PATHINFO_FILENAME);
        $base = preg_replace('/_mp$/', '', $base);

        // Las dos nomenclaturas que conviven en disco (ver project_imagen_estandar).
        foreach (['_medium', '-medium'] as $suffix) {
            $candidate = $ext ? "{$base}{$suffix}.{$ext}" : "{$base}{$suffix}";
            try {
                if ($disk->exists(ImageProcessingService::BASE_DIR . '/' . $candidate)) {
                    return str_replace($file, $candidate, $imageUrl);
                }
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }
};
