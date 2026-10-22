<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\BackgroundRemoval\BackgroundRemovalException;
use App\Services\Tenant\BackgroundRemoval\BackgroundRemovalService;
use App\Services\Tenant\ImageProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Quitar el fondo de la imagen principal de un producto.
 *
 * No guarda nada en el producto: devuelve un temporal nuevo, igual que
 * `ItemController::upload`, y el vendedor decide si se queda con él. Si lo
 * acepta, el formulario manda ese `temp_path` al guardar y la imagen entra por
 * `ImageProcessingService::processAndStore` como cualquier otra.
 */
class ItemBackgroundController extends Controller
{
    public function __construct(private BackgroundRemovalService $service)
    {
    }

    /** ¿Se enseña el botón? Y cuánto cupo le queda a la tienda. */
    public function status()
    {
        return [
            'enabled'   => $this->service->enabled(),
            'used'      => $this->service->used(),
            'limit'     => $this->service->limit(),
            'remaining' => $this->service->remaining(),
        ];
    }

    /**
     * Entrada: `temp_path` (una foto recién subida y aún sin guardar) o
     * `image` (la principal ya guardada del producto).
     */
    public function remove(Request $request)
    {
        $contenido = $this->leerOrigen($request->input('temp_path'), $request->input('image'));
        if ($contenido === null) {
            return ['success' => false, 'message' => 'No encontramos la imagen. Vuelve a subirla e inténtalo otra vez.'];
        }

        try {
            $resultado = $this->service->remove($contenido);
        } catch (BackgroundRemovalException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        // Mismo prefijo que los temporales de `upload_image`: es lo que
        // `leerOrigen` acepta si el vendedor vuelve a intentarlo sobre este.
        $temp = tempnam(sys_get_temp_dir(), 'img_');
        file_put_contents($temp, $resultado);

        $validacion = ImageProcessingService::validate($temp);
        if (!$validacion['success']) {
            @unlink($temp);
            Log::warning('[ItemBackground] el proveedor devolvió algo que no es imagen', ['msg' => $validacion['message']]);

            return ['success' => false, 'message' => 'No se pudo quitar el fondo de esta foto. Prueba con otra.'];
        }

        $mime = mime_content_type($temp) ?: 'image/png';

        return [
            'success' => true,
            'data'    => [
                'temp_path'  => $temp,
                'filename'   => ImageProcessingService::sanitizeFilename('sin-fondo'),
                'temp_image' => 'data:' . $mime . ';base64,' . base64_encode($resultado),
                'remaining'  => $this->service->remaining(),
            ],
        ];
    }

    /**
     * Los bytes de la imagen de origen, o null.
     *
     * Las dos entradas vienen del navegador, así que se acotan: el temporal
     * tiene que estar en el directorio temporal y llevar el prefijo de
     * nuestras subidas, y la imagen guardada se busca por su nombre (sin
     * ruta) en la carpeta de productos. Si no, cualquiera con acceso al
     * panel podría mandar a un tercero cualquier archivo del servidor.
     */
    private function leerOrigen(?string $tempPath, ?string $image): ?string
    {
        if ($tempPath) {
            $real    = realpath($tempPath);
            $tempDir = realpath(sys_get_temp_dir());

            if ($real && $tempDir
                && dirname($real) === $tempDir
                // 'img', no 'img_': en Windows tempnam() solo usa las tres
                // primeras letras del prefijo. En Linux cubre 'img_' e 'imgq_'.
                && str_starts_with(basename($real), 'img')
                && is_file($real)) {
                return file_get_contents($real) ?: null;
            }

            return null;
        }

        if ($image) {
            $nombre = basename($image);
            if ($nombre === '' || $nombre === 'imagen-no-disponible.jpg') {
                return null;
            }

            $disk = Storage::disk(ImageProcessingService::disk());
            $ruta = ImageProcessingService::BASE_DIR . '/' . $nombre;

            return $disk->exists($ruta) ? $disk->get($ruta) : null;
        }

        return null;
    }
}
