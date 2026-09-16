<?php

namespace App\Jobs\Marketplace;

use App\Jobs\TenantAwareJob;
use App\Models\Tenant\Item;
use App\Services\Marketplace\SagaImageImporter;
use Illuminate\Support\Facades\Log;

/**
 * Descarga en segundo plano las imágenes de UN producto importado de Saga.
 *
 * Por qué existe: el panel importa por lotes dentro de una petición HTTP, y la
 * descarga + reencode de hasta 9 imágenes por producto (5 tamaños cada una)
 * hacía que el lote excediera el timeout del servidor — la importación moría a
 * la mitad y el resto del catálogo no se traía. Ahora el lote sólo escribe datos
 * (rápido) y las imágenes se encolan aquí.
 *
 * Idempotente: SagaImageImporter no pisa la imagen principal real ni duplica la
 * galería, así que reintentarlo es seguro.
 */
class ImportSagaProductImagesJob extends TenantAwareJob
{
    public int $tries = 2;
    public int $backoff = 120;
    public int $timeout = 600;

    public function __construct(
        public int $itemId,
        public string $mainUrl,
        public array $galleryUrls,
        public string $name,
        public bool $isNew
    ) {
        parent::__construct();

        // Cola propia: el scheduler la drena cada minuto (ver Console\Kernel).
        // Así las imágenes no dependen de que haya un supervisor corriendo
        // `queue:work` genérico, ni arrastran el backlog de otras colas.
        $this->onQueue('saga-images');
    }

    public function handle(): void
    {
        $item = Item::find($this->itemId);
        if (!$item) {
            return; // el producto se borró entre el dispatch y el worker
        }

        $result = SagaImageImporter::apply(
            $item,
            $this->mainUrl,
            $this->galleryUrls,
            $this->name,
            $this->isNew
        );

        if (!empty($result['errors'])) {
            Log::channel('payments')->warning('Saga import: imágenes con fallos', [
                'item_id'   => $this->itemId,
                'item_code' => $item->item_code,
                'errors'    => array_slice($result['errors'], 0, 5),
            ]);
        }
    }
}
