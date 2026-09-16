<?php

namespace App\Services\Marketplace;

use App\Models\Tenant\Item;
use App\Models\Tenant\ItemImage;
use App\Services\Tenant\ImageProcessingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Descarga y guarda las imágenes de un producto de Saga Falabella.
 *
 * Vive aparte del importador porque el trabajo de imágenes es LENTO (una
 * descarga HTTP + 5 reencodes por imagen, hasta 9 imágenes por producto) y no
 * cabe dentro del lote HTTP del panel: ahí se encola (ImportSagaProductImagesJob)
 * y aquí se ejecuta. El importador por CLI lo llama directo (síncrono).
 *
 * Idempotente: no pisa una imagen principal real ya existente y sólo siembra la
 * galería si el item aún no tiene imágenes adicionales (permite backfill).
 */
class SagaImageImporter
{
    /** Tope de imágenes de galería por producto (además de la principal). */
    const MAX_GALLERY = 8;

    /**
     * Normaliza las URLs de imagen que vienen en el producto de Saga.
     * `Images.Image` puede llegar como array o como string suelto.
     *
     * @return array{main: string, gallery: string[]}
     */
    public static function extractUrls(array $p): array
    {
        $raw = data_get($p, 'Images.Image', []);
        if (is_string($raw)) {
            $raw = [$raw];
        }
        $urls = array_values(array_filter(array_map(fn($u) => trim((string) $u), (array) $raw)));

        $main = trim((string) data_get($p, 'MainImage', '')) ?: ($urls[0] ?? '');
        $gallery = array_values(array_filter($urls, fn($u) => $u !== $main));

        return ['main' => $main, 'gallery' => array_slice($gallery, 0, self::MAX_GALLERY)];
    }

    /**
     * Aplica las imágenes al item. No lanza: los fallos se devuelven para que
     * el llamante decida si registrarlos (una imagen rota no invalida el producto).
     *
     * @return array{main: bool, gallery: int, errors: string[]}
     */
    public static function apply(Item $item, string $mainUrl, array $gallery, string $name, bool $isNew): array
    {
        $out = ['main' => false, 'gallery' => 0, 'errors' => []];

        if ($mainUrl === '' && empty($gallery)) {
            return $out;
        }

        // 1) Imagen principal: sólo si es nuevo o aún no tiene una imagen real.
        $needsMain = $isNew || empty($item->image) || $item->image === 'imagen-no-disponible.jpg';
        if ($mainUrl !== '' && $needsMain) {
            $result = self::downloadAndProcess($mainUrl, $name, $item, $out['errors']);
            if ($result) {
                $item->image        = $result['main']   ?? $item->image;
                $item->image_medium = $result['medium'] ?? $item->image_medium;
                $item->image_small  = $result['small']  ?? $item->image_small;
                $item->saveQuietly();
                $out['main'] = true;
            }
        }

        // 2) Galería: sólo si el item aún no tiene imágenes adicionales (evita
        // duplicar al re-correr la importación).
        if ($item->images()->count() === 0) {
            foreach ($gallery as $i => $gurl) {
                $result = self::downloadAndProcess($gurl, $name . '-' . ($i + 2), $item, $out['errors']);
                if ($result && !empty($result['main'])) {
                    ItemImage::create(['item_id' => $item->id, 'image' => $result['main']]);
                    $out['gallery']++;
                }
            }
        }

        return $out;
    }

    /**
     * Descarga una URL de imagen y genera las variantes locales.
     * Devuelve ['main','medium','small'] o null si falla (acumulando el motivo).
     */
    protected static function downloadAndProcess(string $url, string $name, Item $item, array &$errors): ?array
    {
        if ($url === '') {
            return null;
        }

        $tmp = null;
        try {
            $resp = Http::timeout(25)->get($url);
            if (!$resp->successful()) {
                $errors[] = "HTTP {$resp->status()} al descargar {$url}";
                return null;
            }

            $tmp = tempnam(sys_get_temp_dir(), 'saga_img_');
            file_put_contents($tmp, $resp->body());

            $base = ImageProcessingService::sanitizeFilename($name, $item->internal_id ?: 'product');

            return ImageProcessingService::processAndStore($tmp, $base);
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
            Log::channel('payments')->warning("Falabella import image fail [{$item->item_code}]: {$e->getMessage()}", [
                'item_id' => $item->id,
                'url'     => $url,
            ]);
            return null;
        } finally {
            if ($tmp && file_exists($tmp)) {
                @unlink($tmp);
            }
        }
    }
}
