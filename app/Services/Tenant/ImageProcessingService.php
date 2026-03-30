<?php

namespace App\Services\Tenant;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManagerStatic as Image;

/**
 * ImageProcessingService
 *
 * Servicio central para sanitización, procesamiento y almacenamiento de imágenes de productos.
 * Soporta conversión a WEBP, tres tamaños automáticos y compatibilidad con imágenes legacy.
 *
 * Uso básico:
 *   [$main, $medium, $small] = ImageProcessingService::processAndStore($tempPath, $baseName);
 *   $item->image        = $main;
 *   $item->image_medium = $medium;
 *   $item->image_small  = $small;
 */
class ImageProcessingService
{
    // ── Configuración de tamaños ──────────────────────────────────────────────
    const SIZES = [
        'main'   => ['width' => 1200, 'height' => null, 'quality' => 80, 'suffix' => ''],
        'medium' => ['width' => 512,  'height' => null, 'quality' => 75, 'suffix' => '_medium'],
        'small'  => ['width' => 256,  'height' => null, 'quality' => 70, 'suffix' => '_small'],
    ];

    // ── Límites ───────────────────────────────────────────────────────────────
    const MAX_INPUT_BYTES    = 15 * 1024 * 1024; // 15 MB input máximo
    const TARGET_MAX_BYTES   = 300 * 1024;        // 300 KB objetivo para main
    const MIN_QUALITY        = 40;                // calidad mínima al comprimir

    // ── Storage ───────────────────────────────────────────────────────────────
    // MEDIA_DISK env var controla el disco de almacenamiento:
    //   local / dev:   MEDIA_DISK=public   (storage/app/public, symlink /storage)
    //   producción:    MEDIA_DISK=media    (S3 / Cloudflare R2 / MinIO)
    const BASE_DIR = 'uploads/items';

    public static function disk(): string
    {
        return env('MEDIA_DISK', 'public');
    }

    // ── MIME types permitidos ─────────────────────────────────────────────────
    const ALLOWED_MIMES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
    ];

    // ── Extensiones permitidas (para validación de extensión de archivo) ───────
    const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

    // =========================================================================
    // SANITIZACIÓN DE NOMBRES
    // =========================================================================

    /**
     * Genera un nombre de archivo seguro, sin caracteres especiales y sin colisiones.
     *
     * Ejemplos:
     *   "Mi Producto (1).jpg"      → "mi-producto-1-a3f7b2c1"
     *   "laptop_gaming_2024.PNG"   → "laptop-gaming-2024-d9e1f4a2"
     *   "foto con espacios.jpeg"   → "foto-con-espacios-b8c2d3e4"
     *
     * @param  string      $originalName  Nombre original del archivo (con o sin extensión)
     * @param  string|null $prefix        Prefijo opcional (ej: internal_id del producto)
     * @return string                     Nombre seguro SIN extensión (se añade .webp al guardar)
     */
    public static function sanitizeFilename(string $originalName, ?string $prefix = null): string
    {
        // 1. Quitar extensión
        $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
        if (empty($nameWithoutExt)) {
            $nameWithoutExt = 'item';
        }

        // 2. Transliterar a ASCII (ñ→n, á→a, ü→u, etc.)
        if (function_exists('transliterator_transliterate')) {
            $slug = transliterator_transliterate('Any-Latin; Latin-ASCII', $nameWithoutExt);
            if ($slug === false) $slug = $nameWithoutExt;
        } elseif (function_exists('iconv')) {
            $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nameWithoutExt);
            if ($slug === false) $slug = $nameWithoutExt;
        } else {
            // Fallback manual para caracteres latinos comunes
            $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
                    'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N',
                    'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u',
                    'â'=>'a','ê'=>'e','î'=>'i','ô'=>'o','û'=>'u',
                    'ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o',
                    'ã'=>'a','õ'=>'o','ç'=>'c','ý'=>'y'];
            $slug = strtr($nameWithoutExt, $map);
        }

        // 3. Normalizar: minúsculas, reemplazar cualquier char no alfanumérico por guión
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        $slug = preg_replace('/-{2,}/', '-', $slug); // colapsar guiones múltiples

        // 4. Límite de 30 caracteres
        $slug = substr($slug, 0, 30);
        $slug = rtrim($slug, '-');
        $slug = $slug ?: 'item';

        // 5. Agregar prefijo (internal_id o descripción corta del producto)
        if ($prefix) {
            $cleanPrefix = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $prefix));
            $cleanPrefix = substr(trim($cleanPrefix, '-'), 0, 15);
            $slug        = $cleanPrefix . '-' . $slug;
        }

        // 6. UUID corto (8 chars) para evitar colisiones 100%
        $uuid      = (string) Str::uuid();
        $shortUuid = substr(str_replace('-', '', $uuid), 0, 8);

        return $slug . '-' . $shortUuid;
    }

    /**
     * Extrae la extensión real del archivo desde su contenido MIME (no del nombre).
     * Evita que nombres como "virus.jpg.exe" pasen como imágenes.
     */
    public static function getExtensionFromMime(string $mime): ?string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            'image/bmp'  => 'bmp',
        ];

        return $map[$mime] ?? null;
    }

    // =========================================================================
    // VALIDACIÓN
    // =========================================================================

    /**
     * Valida que el archivo temporal sea una imagen real y segura.
     * Retorna ['success'=>true] o ['success'=>false, 'message'=>'...'].
     */
    public static function validate(string $tempPath): array
    {
        if (!file_exists($tempPath) || !is_readable($tempPath)) {
            return ['success' => false, 'message' => 'Archivo temporal no encontrado.'];
        }

        $size = filesize($tempPath);
        if ($size === 0) {
            return ['success' => false, 'message' => 'El archivo está vacío.'];
        }

        if ($size > self::MAX_INPUT_BYTES) {
            $mb = round($size / 1024 / 1024, 1);
            return ['success' => false, 'message' => "Archivo demasiado grande: {$mb} MB (máximo 15 MB)."];
        }

        // Validar MIME real desde contenido del archivo (no del nombre)
        // finfo_file() es más confiable que mime_content_type() porque usa libmagic
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $tempPath);
            finfo_close($finfo);
        } else {
            $mime = mime_content_type($tempPath);
        }

        if ($mime === false || !in_array($mime, self::ALLOWED_MIMES)) {
            return ['success' => false, 'message' => "Tipo de archivo no permitido: {$mime}. Use JPG, PNG, GIF o WEBP."];
        }

        // Rechazo explícito de SVG aunque ALLOWED_MIMES ya lo excluye (doble seguro contra XSS)
        if (stripos($mime, 'svg') !== false || stripos($mime, 'xml') !== false) {
            return ['success' => false, 'message' => 'Los archivos SVG/XML no están permitidos.'];
        }

        // Intentar cargar la imagen con Intervention (detecta archivos corruptos)
        try {
            $test = Image::make($tempPath);
            if ($test->width() < 1 || $test->height() < 1) {
                return ['success' => false, 'message' => 'La imagen está corrupta o tiene dimensiones inválidas.'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'No se pudo procesar la imagen: ' . $e->getMessage()];
        }

        return ['success' => true];
    }

    // =========================================================================
    // PROCESAMIENTO Y ALMACENAMIENTO
    // =========================================================================

    /**
     * Procesa una imagen desde ruta temporal:
     *   - Convierte a WEBP
     *   - Genera 3 tamaños: main (1200px), medium (512px), small (256px)
     *   - Comprime main a ≤300KB si es posible
     *   - Almacena en disco 'public'
     *   - Elimina el archivo temporal
     *
     * @param  string $tempPath  Ruta al archivo temporal
     * @param  string $baseName  Nombre base (sin extensión), generado por sanitizeFilename()
     * @return array             ['main'=>'...webp', 'medium'=>'...webp', 'small'=>'...webp']
     * @throws \RuntimeException Si la validación falla o no se puede procesar
     */
    public static function processAndStore(string $tempPath, string $baseName): array
    {
        // Validar antes de procesar
        $validation = self::validate($tempPath);
        if (!$validation['success']) {
            throw new \RuntimeException($validation['message']);
        }

        $results = [
            'main'   => null,
            'medium' => null,
            'small'  => null,
        ];

        $canUseWebp = self::supportsWebp();

        foreach (self::SIZES as $key => $config) {
            try {
                $img = Image::make($tempPath);

                // Redimensionar manteniendo aspect ratio, sin agrandar imágenes pequeñas
                $img->resize($config['width'], $config['height'], function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });

                $filename = self::BASE_DIR . '/' . $baseName . $config['suffix'];

                if ($canUseWebp) {
                    // Formato WEBP
                    $filename .= '.webp';
                    $encoded   = $img->encode('webp', $config['quality']);

                    // Para imagen principal: reducir calidad iterativamente si supera 300KB
                    if ($key === 'main') {
                        $encoded = self::compressToTarget($img, 'webp', $config['quality']);
                    }
                } else {
                    // Fallback a JPG si el servidor no soporta WEBP
                    $filename .= '.jpg';
                    $encoded   = $img->encode('jpg', $config['quality']);

                    if ($key === 'main') {
                        $encoded = self::compressToTarget($img, 'jpg', $config['quality']);
                    }
                }

                Storage::disk(self::disk())->put($filename, (string) $encoded);
                $results[$key] = basename($filename);

                // Liberar memoria
                $img->destroy();

            } catch (\Exception $e) {
                Log::warning("[ImageProcessingService] Error procesando tamaño [{$key}] de {$baseName}: " . $e->getMessage());
                // No abortar — continuar con los otros tamaños
            }
        }

        // Limpiar temporal
        @unlink($tempPath);

        if ($results['main'] === null) {
            throw new \RuntimeException('No se pudo procesar ningún tamaño de la imagen.');
        }

        return $results;
    }

    /**
     * Comprime la imagen reduciendo calidad hasta alcanzar TARGET_MAX_BYTES o MIN_QUALITY.
     */
    private static function compressToTarget($img, string $format, int $startQuality): \Intervention\Image\Image
    {
        $quality = $startQuality;
        $encoded = $img->encode($format, $quality);

        while (
            strlen((string) $encoded) > self::TARGET_MAX_BYTES
            && $quality > self::MIN_QUALITY
        ) {
            $quality -= 10;
            $encoded  = $img->encode($format, $quality);
        }

        return $encoded;
    }

    /**
     * Detecta si el servidor soporta WEBP (GD con soporte WEBP o Imagick).
     */
    public static function supportsWebp(): bool
    {
        static $result = null;
        if ($result !== null) {
            return $result;
        }

        // GD con soporte WEBP
        if (function_exists('imagewebp')) {
            return $result = true;
        }

        // Imagick con soporte WEBP
        if (extension_loaded('imagick')) {
            $formats = \Imagick::queryFormats('WEBP');
            return $result = !empty($formats);
        }

        return $result = false;
    }

    // =========================================================================
    // HELPERS PARA URLs
    // =========================================================================

    /**
     * Retorna la URL pública de una imagen de producto.
     * Compatible con imágenes legacy (.jpg) y nuevas (.webp).
     */
    public static function getUrl(?string $filename): string
    {
        if (empty($filename) || $filename === 'imagen-no-disponible.jpg') {
            return asset('/logo/imagen-no-disponible.jpg');
        }

        return Storage::disk(self::disk())->url(self::BASE_DIR . '/' . $filename);
    }

    // =========================================================================
    // CONSULTAS
    // =========================================================================

    /**
     * Retorna query builder con los productos sin imagen (para reportes/dashboard).
     * Compatible con el multitenancy — se ejecuta en la conexión activa del tenant.
     *
     * SQL equivalente:
     *   SELECT id, description, internal_id, category_id, active
     *   FROM items
     *   WHERE image IS NULL OR image = '' OR image = 'imagen-no-disponible.jpg'
     *   ORDER BY description
     */
    public static function queryItemsWithoutImage(): \Illuminate\Database\Query\Builder
    {
        return \DB::connection('tenant')->table('items')
            ->select('id', 'description', 'internal_id', 'category_id', 'active', 'updated_at')
            ->where(function ($q) {
                $q->whereNull('image')
                  ->orWhere('image', '')
                  ->orWhere('image', 'imagen-no-disponible.jpg');
            })
            ->orderBy('description');
    }

    /**
     * Cuenta los productos sin imagen (útil para badge en dashboard).
     */
    public static function countItemsWithoutImage(): int
    {
        return self::queryItemsWithoutImage()->count();
    }
}
