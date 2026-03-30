<?php

namespace App\Console\Commands;

use App\Models\Tenant\Item;
use App\Services\Tenant\ImageProcessingService;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Migra imágenes de productos del disco local 'public' al disco cloud configurado.
 *
 * Uso:
 *   php artisan images:migrate-to-cloud                   -- dry-run (solo reporta)
 *   php artisan images:migrate-to-cloud --execute         -- copia archivos reales
 *   php artisan images:migrate-to-cloud --tenant=uuid     -- solo un tenant específico
 *   php artisan images:migrate-to-cloud --from=public     -- disco origen (default: public)
 *   php artisan images:migrate-to-cloud --to=media        -- disco destino (default: MEDIA_DISK)
 *
 * Prerequisitos:
 *   - Configurar variables en .env: AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_BUCKET, etc.
 *   - Configurar MEDIA_DISK=media en .env
 *   - Asegurarse de que el disco destino tenga visibility=public
 */
class MigrateImagesToCloud extends Command
{
    protected $signature = 'images:migrate-to-cloud
                            {--execute     : Ejecutar la migración real (sin esto es dry-run)}
                            {--tenant=     : UUID del tenant a migrar (omitir = todos)}
                            {--from=public : Disco origen donde están las imágenes locales}
                            {--to=         : Disco destino (default: env MEDIA_DISK)}
                            {--skip-existing : No sobreescribir archivos que ya existen en destino}';

    protected $description = 'Migra imágenes de productos del disco local al almacenamiento cloud (S3/R2/MinIO)';

    private int $copied  = 0;
    private int $skipped = 0;
    private int $missing = 0;
    private int $errors  = 0;

    public function handle(Environment $tenancy): int
    {
        $execute      = $this->option('execute');
        $tenantUuid   = $this->option('tenant');
        $fromDisk     = $this->option('from') ?: 'public';
        $toDisk       = $this->option('to')   ?: env('MEDIA_DISK', 'public');
        $skipExisting = $this->option('skip-existing');

        if ($fromDisk === $toDisk) {
            $this->error("El disco origen y destino son el mismo: [{$fromDisk}]. Nada que migrar.");
            return 1;
        }

        if (!$execute) {
            $this->warn('⚠ Modo DRY-RUN — no se copiará ningún archivo. Use --execute para migrar.');
        }

        $this->info("Migración: disco [{$fromDisk}] → [{$toDisk}]");
        $this->newLine();

        $query = Website::query();
        if ($tenantUuid) {
            $query->where('uuid', $tenantUuid);
        }

        $bar = null;
        $totalTenants = $query->count();
        $this->info("Tenants a procesar: {$totalTenants}");

        $query->chunk(10, function ($websites) use ($tenancy, $fromDisk, $toDisk, $execute, $skipExisting) {
            foreach ($websites as $website) {
                try {
                    $tenancy->tenant($website);
                    $this->migrateTenant($website->uuid, $fromDisk, $toDisk, $execute, $skipExisting);
                } catch (\Throwable $e) {
                    $this->error("Error en tenant [{$website->uuid}]: {$e->getMessage()}");
                    Log::error('[images:migrate-to-cloud] Error en tenant', [
                        'tenant' => $website->uuid,
                        'error'  => $e->getMessage(),
                    ]);
                } finally {
                    $tenancy->tenant(null);
                }
            }
        });

        $this->newLine();
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Archivos copiados',          $this->copied],
                ['Archivos ya existentes (omitidos)', $this->skipped],
                ['Archivos no encontrados en origen', $this->missing],
                ['Errores de copia',           $this->errors],
            ]
        );

        if (!$execute && ($this->copied + $this->missing) > 0) {
            $this->newLine();
            $this->warn('Dry-run completado. Ejecute con --execute para aplicar la migración.');
        }

        return 0;
    }

    private function migrateTenant(string $uuid, string $fromDisk, string $toDisk, bool $execute, bool $skipExisting): void
    {
        $this->line("  → Tenant [{$uuid}]");

        // Recopilar todos los archivos de imagen referenciados en la DB del tenant
        $filenames = collect();

        Item::select('image', 'image_medium', 'image_small')
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->where('image', '!=', 'imagen-no-disponible.jpg')
            ->chunk(200, function ($items) use (&$filenames) {
                foreach ($items as $item) {
                    foreach (['image', 'image_medium', 'image_small'] as $col) {
                        $val = $item->{$col};
                        if (!empty($val) && $val !== 'imagen-no-disponible.jpg') {
                            $filenames->push($val);
                        }
                    }
                }
            });

        $unique = $filenames->unique()->values();
        $this->line("     Archivos únicos encontrados en DB: {$unique->count()}");

        foreach ($unique as $filename) {
            $path = ImageProcessingService::BASE_DIR . '/' . $filename;
            $this->migrateFile($path, $fromDisk, $toDisk, $execute, $skipExisting, $uuid);
        }
    }

    private function migrateFile(
        string $path,
        string $fromDisk,
        string $toDisk,
        bool   $execute,
        bool   $skipExisting,
        string $tenantUuid
    ): void {
        $from = Storage::disk($fromDisk);
        $to   = Storage::disk($toDisk);

        // Verificar existencia en origen
        if (!$from->exists($path)) {
            $this->warn("     [MISSING] {$path}");
            $this->missing++;
            return;
        }

        // Si skip-existing y ya existe en destino, saltar
        if ($skipExisting && $to->exists($path)) {
            $this->skipped++;
            return;
        }

        if (!$execute) {
            // Dry-run: solo reportar
            $size = $from->size($path);
            $this->line("     [DRY-RUN] {$path} (" . round($size / 1024, 1) . " KB)");
            $this->copied++;
            return;
        }

        // Copiar stream para no cargar todo en memoria
        try {
            $stream = $from->readStream($path);
            if ($stream === null) {
                $this->error("     [ERROR] No se pudo abrir stream: {$path}");
                $this->errors++;
                return;
            }

            $to->writeStream($path, $stream, ['visibility' => 'public']);

            if (is_resource($stream)) {
                fclose($stream);
            }

            $this->copied++;
            $this->line("     [OK] {$path}");

        } catch (\Throwable $e) {
            $this->error("     [ERROR] {$path}: {$e->getMessage()}");
            Log::error('[images:migrate-to-cloud] Error copiando archivo', [
                'path'   => $path,
                'tenant' => $tenantUuid,
                'error'  => $e->getMessage(),
            ]);
            $this->errors++;
        }
    }
}
