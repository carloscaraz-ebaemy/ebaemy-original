<?php

namespace App\Services\Marketplace;

/**
 * Enlace a un video ya publicado en YouTube (incluido Shorts), TikTok o
 * Instagram (Reels), validado y normalizado.
 *
 * Es la ÚNICA puerta por la que un enlace pegado por una tienda llega a un
 * iframe: de la URL solo se queda el proveedor y el id del video, ambos
 * comprobados con una expresión estricta, y el `src` del reproductor se arma
 * SIEMPRE desde ese id (`embedUrl()`), nunca con la URL que pegó el usuario.
 *
 * Sin red: los enlaces cortos de TikTok (vm.tiktok.com/…) lanzan una
 * VideoLinkException con `shortUrl` para que VideoLinkResolver los expanda.
 */
final class VideoLink
{
    public const PROVIDERS = [
        'youtube'   => 'YouTube',
        'tiktok'    => 'TikTok',
        'instagram' => 'Instagram',
    ];

    /** Expresiones del id por proveedor: lo único que se acepta en un src. */
    private const ID_PATTERNS = [
        'youtube'   => '/^[A-Za-z0-9_-]{11}$/',
        'tiktok'    => '/^\d{8,25}$/',
        'instagram' => '/^[A-Za-z0-9_-]{5,40}$/',
    ];

    private const YOUTUBE_HOSTS   = ['youtube.com', 'www.youtube.com', 'm.youtube.com'];
    private const TIKTOK_HOSTS    = ['tiktok.com', 'www.tiktok.com', 'm.tiktok.com'];
    private const TIKTOK_SHORT    = ['vm.tiktok.com', 'vt.tiktok.com'];
    private const INSTAGRAM_HOSTS = ['instagram.com', 'www.instagram.com'];

    public const MAX_LENGTH = 500;

    public function __construct(
        public readonly string $provider,
        public readonly string $id,
        /** URL canónica, reconstruida desde el id (no la que pegó el usuario). */
        public readonly string $url,
        /** vertical (Shorts, TikTok, Reels) | horizontal (YouTube normal). */
        public readonly string $orientation,
    ) {}

    /**
     * Valida y normaliza lo que pegó la tienda. Acepta el texto que comparte
     * la app («Mira este video https://vm.tiktok.com/…»): se queda con la
     * primera URL.
     *
     * @throws VideoLinkException con un mensaje listo para mostrar.
     */
    public static function parse(?string $input): self
    {
        $raw = trim((string) $input);
        if ($raw === '') {
            throw new VideoLinkException('Pega el enlace del video.');
        }
        if (mb_strlen($raw) > 2000) {
            throw new VideoLinkException('Ese texto es demasiado largo. Pega solo el enlace del video.');
        }

        // Primera URL del texto; sin esquema, el primer «palabra» con un punto.
        if (preg_match('~https?://[^\s<>"\']+~i', $raw, $m)) {
            $candidate = $m[0];
        } else {
            $candidate = preg_split('/\s+/', $raw)[0];
            if (!str_contains($candidate, '.')) {
                throw new VideoLinkException(self::genericError());
            }
            $candidate = 'https://' . ltrim($candidate, '/');
        }
        $candidate = rtrim($candidate, '.,;)!»');

        if (strlen($candidate) > self::MAX_LENGTH) {
            throw new VideoLinkException('El enlace es demasiado largo. Cópialo de nuevo desde el botón «Compartir» del video.');
        }

        $parts = parse_url($candidate);
        if ($parts === false || empty($parts['host'])) {
            throw new VideoLinkException(self::genericError());
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && !in_array((int) $parts['port'], [80, 443], true))) {
            throw new VideoLinkException(self::genericError());
        }

        $host = rtrim(strtolower($parts['host']), '.');
        $path = $parts['path'] ?? '/';
        parse_str($parts['query'] ?? '', $query);

        if (in_array($host, self::YOUTUBE_HOSTS, true) || $host === 'youtu.be') {
            return self::youtube($host, $path, $query);
        }
        if (in_array($host, self::TIKTOK_HOSTS, true) || in_array($host, self::TIKTOK_SHORT, true)) {
            return self::tiktok($host, $path);
        }
        if (in_array($host, self::INSTAGRAM_HOSTS, true)) {
            return self::instagram($path);
        }

        throw new VideoLinkException(self::genericError());
    }

    /** ¿Este id es válido para el proveedor? */
    public static function validId(?string $provider, ?string $id): bool
    {
        $re = self::ID_PATTERNS[$provider] ?? null;
        return $re !== null && is_string($id) && preg_match($re, $id) === 1;
    }

    /**
     * src del iframe, armado desde el id validado. null si el par no es
     * válido: así una fila manipulada en la base nunca llega a un iframe.
     */
    public static function embedUrl(?string $provider, ?string $id): ?string
    {
        if (!self::validId($provider, $id)) {
            return null;
        }

        return match ($provider) {
            'youtube'   => 'https://www.youtube-nocookie.com/embed/' . $id,
            'tiktok'    => 'https://www.tiktok.com/player/v1/' . $id,
            'instagram' => 'https://www.instagram.com/reel/' . $id . '/embed',
        };
    }

    /** Miniatura que no necesita red: solo YouTube la publica en una URL fija. */
    public static function staticThumbnail(?string $provider, ?string $id): ?string
    {
        return $provider === 'youtube' && self::validId('youtube', $id)
            ? 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg'
            : null;
    }

    public function embed(): string
    {
        return (string) self::embedUrl($this->provider, $this->id);
    }

    public function label(): string
    {
        return self::PROVIDERS[$this->provider];
    }

    public static function genericError(): string
    {
        return 'Ese enlace no es de un video de TikTok, YouTube o Instagram. '
            . 'Pega el enlace que sale al tocar «Compartir» › «Copiar enlace» en el video '
            . '(por ejemplo youtube.com/shorts/…, tiktok.com/@tu_tienda/video/… o instagram.com/reel/…).';
    }

    // ── Por proveedor ────────────────────────────────────────────────────

    private static function youtube(string $host, string $path, array $query): self
    {
        $id = null;
        $vertical = false;

        if ($host === 'youtu.be') {
            if (preg_match('~^/([^/]+)/?$~', $path, $m)) {
                $id = $m[1];
            }
        } elseif (preg_match('~^/shorts/([^/]+)/?$~', $path, $m)) {
            $id = $m[1];
            $vertical = true;
        } elseif (rtrim($path, '/') === '/watch') {
            $id = is_string($query['v'] ?? null) ? $query['v'] : null;
        } else {
            throw new VideoLinkException('Ese enlace de YouTube no es de un video. Abre el video y usa «Compartir» › «Copiar enlace».');
        }

        if (!self::validId('youtube', $id)) {
            throw new VideoLinkException('No reconocemos el video en ese enlace de YouTube. Cópialo de nuevo desde «Compartir».');
        }

        return new self(
            'youtube',
            $id,
            $vertical ? 'https://www.youtube.com/shorts/' . $id : 'https://www.youtube.com/watch?v=' . $id,
            $vertical ? 'vertical' : 'horizontal',
        );
    }

    private static function tiktok(string $host, string $path): self
    {
        // Enlace corto: lo resuelve VideoLinkResolver siguiendo la redirección.
        $short = null;
        if (in_array($host, self::TIKTOK_SHORT, true) && preg_match('~^/([A-Za-z0-9]{4,20})/?$~', $path, $m)) {
            $short = 'https://' . $host . '/' . $m[1] . '/';
        } elseif (in_array($host, self::TIKTOK_HOSTS, true) && preg_match('~^/t/([A-Za-z0-9]{4,20})/?$~', $path, $m)) {
            $short = 'https://www.tiktok.com/t/' . $m[1] . '/';
        }
        if ($short !== null) {
            throw VideoLinkException::needsResolve($short);
        }

        if (in_array($host, self::TIKTOK_HOSTS, true)
            && preg_match('~^/@([A-Za-z0-9._-]{1,64})/(video|photo)/(\d{8,25})/?$~', $path, $m)) {
            if ($m[2] === 'photo') {
                throw new VideoLinkException('Ese enlace de TikTok es una publicación de fotos, no un video.');
            }
            return new self('tiktok', $m[3], 'https://www.tiktok.com/@' . $m[1] . '/video/' . $m[3], 'vertical');
        }

        throw new VideoLinkException('Ese enlace de TikTok no es de un video. Abre el video y usa «Compartir» › «Copiar enlace».');
    }

    private static function instagram(string $path): self
    {
        if (preg_match('~^/(?:[A-Za-z0-9._]{1,40}/)?reels?/([A-Za-z0-9_-]{5,40})/?$~', $path, $m)) {
            return new self('instagram', $m[1], 'https://www.instagram.com/reel/' . $m[1] . '/', 'vertical');
        }
        if (preg_match('~^/(?:[A-Za-z0-9._]{1,40}/)?(p|tv)/~', $path)) {
            throw new VideoLinkException('De Instagram solo se aceptan Reels. Abre el Reel y copia su enlace (instagram.com/reel/…).');
        }

        throw new VideoLinkException('Ese enlace de Instagram no es de un Reel. Abre el Reel y usa «Compartir» › «Copiar enlace».');
    }
}
