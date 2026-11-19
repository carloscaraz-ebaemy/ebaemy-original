<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * La parte con red de los videos por enlace:
 *  - expande los enlaces cortos de TikTok (vm.tiktok.com/…) siguiendo la
 *    redirección A MANO, solo hacia hosts de TikTok;
 *  - pide el oEmbed del proveedor con timeout corto y usa SOLO el título y
 *    la miniatura (nunca su `html`);
 *  - descarga la miniatura de TikTok/Instagram a nuestro disco: las URL de su
 *    CDN caducan en días y la tarjeta se quedaría sin foto.
 *
 * Si un proveedor no responde, nada se rompe: el video se guarda igual y en
 * pantalla se usa la foto del producto como póster.
 */
class VideoLinkResolver
{
    public const TIMEOUT = 4;          // segundos por petición
    public const CONNECT_TIMEOUT = 3;
    public const MAX_THUMB_BYTES = 3 * 1024 * 1024;

    /** Carpeta pública central (sirve en ebaemy.com y en cada subdominio). */
    public const THUMB_DIR = 'promo-videos';

    /** CDNs de los que aceptamos descargar una miniatura. */
    private const THUMB_HOST_SUFFIXES = [
        '.tiktokcdn.com', '.tiktokcdn-us.com', '.tiktokcdn-eu.com', '.ibyteimg.com',
        '.cdninstagram.com', '.fbcdn.net', '.ytimg.com',
    ];

    private const UA = 'Mozilla/5.0 (compatible; ebaemy-video-check/1.0)';

    /**
     * Valida el enlace y, si es corto, lo expande.
     *
     * @throws VideoLinkException
     */
    public function resolve(?string $input): VideoLink
    {
        try {
            return VideoLink::parse($input);
        } catch (VideoLinkException $e) {
            if ($e->shortUrl === null) {
                throw $e;
            }
            $long = $this->expandTikTok($e->shortUrl);
            if ($long === null) {
                throw new VideoLinkException(
                    'No pudimos abrir ese enlace corto de TikTok. Ábrelo en tu celular, '
                    . 'toca «Compartir» › «Copiar enlace» desde el navegador y pega el enlace largo '
                    . '(tiktok.com/@tu_tienda/video/…).'
                );
            }
            return VideoLink::parse($long);
        }
    }

    /**
     * Título y miniatura del proveedor (oEmbed). Nunca lanza: si no responde
     * devuelve nulls. YouTube: la miniatura es fija; del oEmbed solo el título.
     *
     * @return array{title:?string, thumbnail:?string}
     */
    public function metadata(VideoLink $link): array
    {
        $out = ['title' => null, 'thumbnail' => VideoLink::staticThumbnail($link->provider, $link->id)];

        $endpoint = match ($link->provider) {
            'youtube'   => 'https://www.youtube.com/oembed?format=json&url=' . rawurlencode($link->url),
            'tiktok'    => 'https://www.tiktok.com/oembed?url=' . rawurlencode($link->url),
            // Instagram exige un token de app de Meta para su oEmbed. Sin él,
            // póster genérico (la foto del producto).
            'instagram' => config('services.instagram_oembed.token')
                ? 'https://graph.facebook.com/v21.0/instagram_oembed?fields=title,thumbnail_url&url='
                    . rawurlencode($link->url) . '&access_token=' . rawurlencode((string) config('services.instagram_oembed.token'))
                : null,
        };
        if ($endpoint === null) {
            return $out;
        }

        try {
            $res = $this->http()->get($endpoint);
            if (!$res->successful()) {
                return $out;
            }
            $json = $res->json();
            if (!is_array($json)) {
                return $out;
            }
            $title = trim(strip_tags((string) ($json['title'] ?? '')));
            if ($title !== '') {
                $out['title'] = mb_substr(preg_replace('/\s+/u', ' ', $title), 0, 300);
            }
            if ($out['thumbnail'] === null && is_string($json['thumbnail_url'] ?? null)
                && $this->allowedThumbHost($json['thumbnail_url'])) {
                $out['thumbnail'] = $json['thumbnail_url'];
            }
        } catch (\Throwable $e) {
            Log::info('[promo-video] oEmbed sin respuesta', ['provider' => $link->provider, 'error' => $e->getMessage()]);
        }

        return $out;
    }

    /**
     * Copia una miniatura remota (TikTok/Instagram) a nuestro disco público.
     * Devuelve la ruta relativa («/storage/promo-videos/…») o null.
     * YouTube no se copia: i.ytimg.com no caduca.
     */
    public function storeThumbnail(VideoLink $link, ?string $remoteUrl): ?string
    {
        if (!$remoteUrl || $link->provider === 'youtube' || !$this->allowedThumbHost($remoteUrl)) {
            return null;
        }

        try {
            $res = $this->http()->get($remoteUrl);
            if (!$res->successful()) {
                return null;
            }
            $bytes = $res->body();
            if ($bytes === '' || strlen($bytes) > self::MAX_THUMB_BYTES) {
                return null;
            }
            $info = @getimagesizefromstring($bytes);
            $ext = match ($info[2] ?? null) {
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG  => 'png',
                IMAGETYPE_WEBP => 'webp',
                default        => null,
            };
            if ($ext === null) {
                return null;
            }

            $name = $link->provider . '-' . $link->id . '.' . $ext;
            $this->disk()->put($name, $bytes);

            return '/storage/' . self::THUMB_DIR . '/' . $name;
        } catch (\Throwable $e) {
            Log::info('[promo-video] miniatura no descargada', ['provider' => $link->provider, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /** Borra una miniatura nuestra (al quitar o cambiar el video). */
    public function deleteThumbnail(?string $relative): void
    {
        $prefix = '/storage/' . self::THUMB_DIR . '/';
        if (!$relative || !str_starts_with($relative, $prefix)) {
            return;
        }
        $name = basename($relative);
        if (preg_match('/^[a-z]+-[A-Za-z0-9_-]+\.(jpg|png|webp)$/', $name)) {
            $this->disk()->delete($name);
        }
    }

    // ── Internos ─────────────────────────────────────────────────────────

    /** Sigue hasta 3 redirecciones, solo dentro de TikTok, y devuelve la URL larga. */
    private function expandTikTok(string $shortUrl): ?string
    {
        $url = $shortUrl;
        for ($i = 0; $i < 3; $i++) {
            try {
                $res = $this->http()->get($url);
            } catch (\Throwable $e) {
                return null;
            }
            $location = $res->header('Location');
            if (!$location) {
                return null;
            }
            if (str_starts_with($location, '/')) {
                $location = 'https://' . parse_url($url, PHP_URL_HOST) . $location;
            }
            $host = strtolower((string) parse_url($location, PHP_URL_HOST));
            if (!in_array($host, ['tiktok.com', 'www.tiktok.com', 'm.tiktok.com', 'vm.tiktok.com', 'vt.tiktok.com'], true)) {
                return null;
            }
            try {
                VideoLink::parse($location);
                return $location;
            } catch (VideoLinkException $e) {
                if ($e->shortUrl === null) {
                    // TikTok a veces manda primero a m.tiktok.com/v/<id>.html.
                    if (preg_match('~/v/(\d{8,25})\.html~', $location, $m)) {
                        return 'https://www.tiktok.com/@_/video/' . $m[1];
                    }
                    return null;
                }
                $url = $e->shortUrl;
            }
        }
        return null;
    }

    private function allowedThumbHost(string $url): bool
    {
        $p = parse_url($url);
        if (($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['port'])) {
            return false;
        }
        $host = '.' . strtolower($p['host']);
        foreach (self::THUMB_HOST_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }
        return false;
    }

    /** Sin seguir redirecciones: cada salto se valida a mano. */
    private function http()
    {
        return Http::withOptions(['allow_redirects' => false])
            ->withHeaders(['User-Agent' => self::UA, 'Accept-Language' => 'es-PE,es;q=0.9'])
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::TIMEOUT);
    }

    private function disk()
    {
        // Disco local explícito sobre storage/app/public: no depende de cómo
        // la tenencia reescriba el disco «public» dentro de una tienda.
        return Storage::build([
            'driver' => 'local',
            'root'   => storage_path('app/public/' . self::THUMB_DIR),
        ]);
    }
}
