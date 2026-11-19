<?php

namespace App\Services\Marketplace;

use App\Services\Tenant\ImageProcessingService;
use Illuminate\Support\Facades\Storage;

/**
 * URL de una versión reducida (`_small` 256 px, `_medium` 512 px, `_mobile`
 * 640 px) de una foto de producto del marketplace, SOLO si el archivo existe.
 *
 * Por qué: las fotos de variante se sincronizan con la URL del archivo
 * principal (hasta 1200 px) y el marketplace las pintaba tal cual en botones
 * de 20 px y en tarjetas de 160 px. Lighthouse medía 1.166 KiB desperdiciados
 * solo en los puntos de variante del inicio (2026-11-19).
 *
 * Todas las fotos viven en un disco plano compartido por los tenants
 * (`uploads/items`, ver MarketplaceListingSyncService::resolveThumbUrl), así
 * que se comprueba el archivo hermano en ese disco. Solo con disco LOCAL: en
 * un disco remoto cada comprobación sería una petición de red por tarjeta, y
 * ahí se devuelve null (la vista cae a la foto completa, como antes).
 *
 * La respuesta se memoriza por petición: el inicio decora ~90 tarjetas.
 */
final class ImageVariantUrl
{
    /** Sufijos de tamaño que se quitan antes de pedir otro. */
    private const KNOWN_SUFFIXES = '/(_mp|_medium|-medium|_small|-small|_mobile)$/';

    /** @var array<string, bool> */
    private static array $exists = [];

    private static ?bool $localDisk = null;

    /**
     * Primera versión que exista de la foto, en el orden de $suffixes.
     *
     * @param  string|null    $url       URL absoluta de la foto (cualquier tamaño)
     * @param  array<string>  $suffixes  p. ej. ['_small', '_medium']
     */
    public static function sibling(?string $url, array $suffixes): ?string
    {
        if (! $url || ! self::localDisk()) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || strpos($path, '/storage/uploads/items/') === false) {
            return null;
        }

        $file = basename($path);
        $ext  = pathinfo($file, PATHINFO_EXTENSION);
        $base = preg_replace(self::KNOWN_SUFFIXES, '', pathinfo($file, PATHINFO_FILENAME));
        $dir  = substr($url, 0, strrpos($url, '/') + 1);

        foreach ($suffixes as $suffix) {
            $candidate = $ext ? "{$base}{$suffix}.{$ext}" : "{$base}{$suffix}";
            if (self::exists($candidate)) {
                return $dir . $candidate;
            }
        }

        return null;
    }

    /** Para las pruebas: olvida lo memorizado. */
    public static function flush(): void
    {
        self::$exists = [];
        self::$localDisk = null;
    }

    private static function exists(string $file): bool
    {
        if (! array_key_exists($file, self::$exists)) {
            try {
                $disk = Storage::disk(ImageProcessingService::disk());
                self::$exists[$file] = is_file($disk->path(ImageProcessingService::BASE_DIR . '/' . $file));
            } catch (\Throwable $e) {
                self::$exists[$file] = false;
            }
        }

        return self::$exists[$file];
    }

    private static function localDisk(): bool
    {
        if (self::$localDisk === null) {
            self::$localDisk = config('filesystems.disks.' . ImageProcessingService::disk() . '.driver') === 'local';
        }

        return self::$localDisk;
    }
}
