<?php

namespace App\Jobs\Tenant;

use App\Jobs\TenantAwareJob;
use App\Models\Tenant\Item;
use App\Models\Tenant\ItemImage;
use App\Services\Tenant\ImageProcessingService;
use Illuminate\Support\Facades\Log;

/**
 * ProcessProductImageJob
 *
 * Procesa y almacena la imagen de un producto en segundo plano (queue).
 * Extiende TenantAwareJob para que el worker restaure la conexión de BD
 * correcta antes de ejecutar handle().
 *
 * Uso (imagen principal):
 *   ProcessProductImageJob::dispatch($tempPath, $baseName, $itemId)
 *       ->onQueue('images');
 *
 * Uso (imagen de galería):
 *   ProcessProductImageJob::dispatch($tempPath, $baseName, $itemId, true)
 *       ->onQueue('images');
 */
class ProcessProductImageJob extends TenantAwareJob
{
    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(
        private string $tempPath,
        private string $baseName,
        private int    $itemId,
        private bool   $isGalleryImage = false,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        try {
            $result = ImageProcessingService::processAndStore($this->tempPath, $this->baseName);

            if ($this->isGalleryImage) {
                if ($result['main']) {
                    ItemImage::create(['item_id' => $this->itemId, 'image' => $result['main']]);
                    Log::info("[ProcessProductImageJob] Galería item {$this->itemId}: " . $result['main']);
                }
                return;
            }

            $item = Item::find($this->itemId);
            if (!$item) {
                Log::warning("[ProcessProductImageJob] Item {$this->itemId} no encontrado.");
                return;
            }

            if ($result['main'])   $item->image        = $result['main'];
            if ($result['medium']) $item->image_medium = $result['medium'];
            if ($result['small'])  $item->image_small  = $result['small'];

            // saveQuietly() para no disparar eventos de stock/kardex
            $item->saveQuietly();

            Log::info("[ProcessProductImageJob] Item {$this->itemId} procesado: " . $result['main']);

        } catch (\Exception $e) {
            Log::error("[ProcessProductImageJob] Error en item {$this->itemId}: " . $e->getMessage());
            $this->fail($e);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[ProcessProductImageJob] FALLO PERMANENTE item {$this->itemId}: " . $exception->getMessage());

        if (file_exists($this->tempPath)) {
            @unlink($this->tempPath);
        }
    }
}
