<?php

namespace App\Console\Commands;

use App\Services\Tenant\ImageProcessingService;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;

/**
 * F6 image-pipeline: genera las versiones por canal faltantes (`_mp` 1080x1080
 * cuadrado y `_mobile` 640px) para items que ya existían antes de que esas
 * variantes formaran parte del pipeline.
 *
 * Uso:
 *   php artisan images:backfill-variants                # todos los tenants
 *   php artisan images:backfill-variants --tenant=uuid  # uno solo
 *   php artisan images:backfill-variants --dry-run      # no escribe nada
 */
class BackfillImageVariants extends Command
{
    protected $signature = 'images:backfill-variants
                            {--tenant= : UUID de un website específico}
                            {--dry-run : No escribe archivos, solo muestra qué haría}';

    protected $description = 'Genera variantes _mp y _mobile para items existentes (F6)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $uuid   = $this->option('tenant');

        $websites = $uuid
            ? Website::where('uuid', $uuid)->get()
            : Website::all();

        if ($websites->isEmpty()) {
            $this->error('No se encontraron tenants.');
            return self::FAILURE;
        }

        $totalProcessed = 0;
        $totalGenerated = 0;
        $totalSkipped   = 0;
        $totalFailed    = 0;

        foreach ($websites as $w) {
            $this->info("\n→ Tenant: {$w->uuid}");
            app(Environment::class)->tenant($w);

            try {
                $items = DB::connection('tenant')->table('items')
                    ->whereNotNull('image')
                    ->where('image', '!=', '')
                    ->where('image', '!=', 'imagen-no-disponible.jpg')
                    ->pluck('image', 'id');
            } catch (\Throwable $e) {
                $this->warn("  ⚠ No se pudo listar items: " . $e->getMessage());
                continue;
            }

            $disk = ImageProcessingService::disk();
            $baseDir = ImageProcessingService::BASE_DIR;

            $variants = ['marketplace' => '_mp', 'mobile' => '_mobile'];

            foreach ($items as $itemId => $filename) {
                $totalProcessed++;

                $mainPath = $baseDir . '/' . $filename;
                if (!Storage::disk($disk)->exists($mainPath)) {
                    $totalSkipped++;
                    continue;
                }

                $missingVariants = [];
                foreach ($variants as $variantKey => $suffix) {
                    $variantFile = $this->injectSuffix($filename, $suffix);
                    if (!Storage::disk($disk)->exists($baseDir . '/' . $variantFile)) {
                        $missingVariants[$variantKey] = $variantFile;
                    }
                }

                if (empty($missingVariants)) {
                    $totalSkipped++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("  [dry] item {$itemId} faltan: " . implode(', ', array_keys($missingVariants)));
                    $totalGenerated += count($missingVariants);
                    continue;
                }

                try {
                    $this->generateMissing($disk, $baseDir, $filename, $missingVariants);
                    $this->line("  ✓ item {$itemId}: " . count($missingVariants) . ' variantes');
                    $totalGenerated += count($missingVariants);
                } catch (\Throwable $e) {
                    $this->warn("  ✗ item {$itemId}: " . $e->getMessage());
                    $totalFailed++;
                }
            }
        }

        $this->info("\n──────────────");
        $this->info("Items procesados : {$totalProcessed}");
        $this->info("Variantes nuevas : {$totalGenerated}" . ($dryRun ? ' (dry-run)' : ''));
        $this->info("Items skip       : {$totalSkipped} (archivos faltantes o ya con variantes)");
        $this->info("Fallos           : {$totalFailed}");

        return self::SUCCESS;
    }

    private function generateMissing(string $disk, string $baseDir, string $filename, array $missing): void
    {
        $sizes = ImageProcessingService::SIZES;

        // Descargar el archivo principal una sola vez (puede ser disco local o cloud)
        $mainBytes = Storage::disk($disk)->get($baseDir . '/' . $filename);
        $temp = tempnam(sys_get_temp_dir(), 'bf_');
        file_put_contents($temp, $mainBytes);

        try {
            foreach ($missing as $variantKey => $targetFilename) {
                $config = $sizes[$variantKey];
                $img = Image::make($temp);
                try { $img->orientate(); } catch (\Throwable $_) { /* sin EXIF */ }

                if (!empty($config['fit_square'])) {
                    $img->fit($config['width'], $config['height'] ?? $config['width']);
                } else {
                    $img->resize($config['width'], $config['height'], function ($c) {
                        $c->aspectRatio();
                        $c->upsize();
                    });
                }

                $format  = pathinfo($targetFilename, PATHINFO_EXTENSION) ?: 'webp';
                $encoded = $img->encode($format, $config['quality']);
                Storage::disk($disk)->put($baseDir . '/' . $targetFilename, (string) $encoded);
                $img->destroy();
            }
        } finally {
            @unlink($temp);
        }
    }

    private function injectSuffix(string $filename, string $suffix): string
    {
        $ext  = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        return $ext ? "{$base}{$suffix}.{$ext}" : "{$base}{$suffix}";
    }
}
