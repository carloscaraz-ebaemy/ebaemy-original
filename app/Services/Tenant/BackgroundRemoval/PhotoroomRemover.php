<?php

namespace App\Services\Tenant\BackgroundRemoval;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Photoroom, Remove Background API (plan Basic, ~0,02 USD por foto).
 *
 *   POST https://sdk.photoroom.com/v1/segment
 *   x-api-key: <clave>      image_file=<archivo>
 *
 * Se pide `bg_color` blanco para que la respuesta llegue ya sobre blanco, y
 * `size=hd` (4 MP): lo que entra ya viene reducido a 1200 px por el navegador,
 * asi que no se pierde nada y la respuesta es mas rapida que con `full`.
 */
class PhotoroomRemover implements BackgroundRemover
{
    private const URL = 'https://sdk.photoroom.com/v1/segment';

    public function __construct(private string $clave)
    {
    }

    public function remove(string $contenido): string
    {
        try {
            $respuesta = Http::timeout(60)
                ->withHeaders(['x-api-key' => $this->clave])
                ->attach('image_file', $contenido, 'producto.jpg')
                ->post(self::URL, [
                    'bg_color' => '#FFFFFF',
                    'size'     => 'hd',
                ]);
        } catch (\Throwable $e) {
            Log::warning('[Photoroom] sin respuesta', ['error' => $e->getMessage()]);

            throw new BackgroundRemovalException('El servicio para quitar el fondo no respondió. Inténtalo en un momento.');
        }

        if ($respuesta->successful()) {
            return $respuesta->body();
        }

        Log::warning('[Photoroom] error', [
            'status' => $respuesta->status(),
            'body'   => mb_substr($respuesta->body(), 0, 300),
        ]);

        throw new BackgroundRemovalException(match (true) {
            $respuesta->status() === 402                            => 'Se agotó el saldo del servicio para quitar fondos. Avisa al administrador.',
            in_array($respuesta->status(), [401, 403], true)        => 'El servicio para quitar fondos no está bien configurado. Avisa al administrador.',
            $respuesta->status() === 400                            => 'No se pudo quitar el fondo de esta foto. Prueba con otra.',
            default                                                 => 'No se pudo quitar el fondo ahora. Inténtalo en un momento.',
        });
    }
}
