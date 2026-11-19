<?php

namespace App\Services\Marketplace;

/**
 * Enlace de video no aceptado. El mensaje se muestra tal cual a la tienda.
 * Con `shortUrl` no es un error: es un enlace corto de TikTok que hay que
 * expandir siguiendo su redirección (VideoLinkResolver).
 */
class VideoLinkException extends \InvalidArgumentException
{
    public ?string $shortUrl = null;

    public static function needsResolve(string $shortUrl): self
    {
        $e = new self('Enlace corto de TikTok: hay que abrirlo para saber de qué video es.');
        $e->shortUrl = $shortUrl;
        return $e;
    }
}
