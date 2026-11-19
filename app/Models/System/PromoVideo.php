<?php

namespace App\Models\System;

use App\Services\Marketplace\VideoLink;
use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Video promocional de UN producto de una tienda. Se publica solo con la
 * aprobación del SuperAdmin y mientras la tienda tenga el extra vigente.
 *
 * Dos clases (`kind`):
 *  - upload: archivo propio (fase 1, cuando haya servicio de video en la nube);
 *  - link: enlace a un video ya publicado en YouTube, TikTok o Instagram
 *    (2026-11-19). `provider` + `provider_video_id` validados; el iframe se
 *    arma con embedSrc(), nunca con una URL guardada tal cual.
 */
class PromoVideo extends Model
{
    use UsesSystemConnection;

    /** Duraciones permitidas, en segundos (decidido 2026-11-17). */
    public const DURACIONES = [5, 15, 30];

    /** Margen del encoder: un «15 s» real puede medir 14,6 o 15,4 s. */
    public const TOLERANCIA_S = 0.5;

    public const ESTADOS = [
        'uploading'      => 'Subiendo',
        'processing'     => 'Procesando',
        'pending_review' => 'Por aprobar',
        'approved'       => 'Publicado',
        'rejected'       => 'Rechazado',
        'hidden'         => 'Oculto',
        'failed'         => 'Falló',
    ];

    protected $fillable = [
        'client_id', 'hostname_id', 'remote_item_id', 'remote_variant_id', 'item_title',
        'duration_seconds', 'aspect', 'status', 'provider', 'provider_video_id',
        'playback_url', 'mp4_url', 'thumbnail_url', 'width', 'height',
        'title', 'description', 'template', 'rejection_reason', 'reviewed_by',
        'kind', 'source_url', 'source_title',
        'reviewed_at', 'published_at', 'is_featured', 'featured_until',
    ];

    protected $casts = [
        'template'       => 'array',
        'is_featured'    => 'boolean',
        'featured_until' => 'date',
        'reviewed_at'    => 'datetime',
        'published_at'   => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function isLink(): bool
    {
        return $this->kind === 'link';
    }

    public function isVertical(): bool
    {
        return $this->aspect !== 'horizontal';
    }

    public function providerLabel(): ?string
    {
        return VideoLink::PROVIDERS[$this->provider] ?? null;
    }

    /**
     * src del reproductor. En los de enlace se reconstruye desde el id
     * validado (null si la fila no cuadra); en los subidos, el playback_url.
     */
    public function embedSrc(): ?string
    {
        if ($this->isLink()) {
            return VideoLink::embedUrl($this->provider, $this->provider_video_id);
        }
        return $this->playback_url;
    }

    /**
     * Póster absoluto. Las miniaturas copiadas a nuestro disco se guardan
     * relativas («/storage/promo-videos/…») y valen en cualquier dominio.
     * Sin miniatura, $fallback (la foto del producto).
     */
    public function posterUrl(?string $fallback = null): ?string
    {
        $t = $this->thumbnail_url;
        if ($t && str_starts_with($t, '/')) {
            return url($t);
        }
        // La foto «imagen no disponible» del ERP no sirve de póster.
        if ($fallback && stripos($fallback, 'imagen-no-disponible') !== false) {
            $fallback = null;
        }
        return $t ?: $fallback;
    }

    /** La duración medida del archivo encaja con una de las permitidas. */
    public static function duracionValida(float $segundos): ?int
    {
        foreach (self::DURACIONES as $d) {
            if (abs($segundos - $d) <= self::TOLERANCIA_S) {
                return $d;
            }
        }
        return null;
    }

    /**
     * Lo que el público puede ver: aprobado, con archivo listo y con el extra
     * de la tienda vigente. Si la tienda deja de pagar, sus videos salen de
     * aquí solos, sin tocar las filas.
     */
    public function scopePublico($query)
    {
        return $query->where('status', 'approved')
            ->whereNotNull('playback_url')
            ->whereIn('client_id', ClientAddon::query()->vigente()
                ->where('addon_key', Addon::PROMO_VIDEOS)
                ->select('client_id'));
    }

    /** Destacados para las páginas de ebaemy (portada, página de tienda). */
    public function scopeDestacado($query)
    {
        return $query->publico()
            ->where('is_featured', true)
            ->where(fn ($q) => $q->whereNull('featured_until')
                ->orWhereDate('featured_until', '>=', now()->toDateString()));
    }

    /**
     * VideoObject de schema.org para el JSON-LD de la ficha del producto.
     * Google pide name, description, thumbnailUrl y uploadDate; duration y
     * contentUrl/embedUrl le permiten mostrar el video en los resultados.
     */
    public function schemaOrg(string $nombreProducto, ?string $urlFicha = null, ?string $posterFallback = null): array
    {
        return array_filter([
            '@type'        => 'VideoObject',
            'name'         => $this->title ?: $nombreProducto,
            'description'  => $this->description ?: ('Video de ' . $nombreProducto),
            'thumbnailUrl' => $this->posterUrl($posterFallback),
            'uploadDate'   => optional($this->published_at ?? $this->created_at)->toIso8601String(),
            // De un enlace no se conoce la duración: mejor omitirla que inventarla.
            'duration'     => $this->duration_seconds ? 'PT' . (int) $this->duration_seconds . 'S' : null,
            'contentUrl'   => $this->mp4_url,
            'embedUrl'     => $this->embedSrc(),
            'url'          => $urlFicha,
        ]);
    }

    /** Lo que ve la tienda en su panel. */
    public function toPanelArray(): array
    {
        return [
            'id'               => $this->id,
            'kind'             => $this->kind,
            'provider'         => $this->provider,
            'provider_label'   => $this->providerLabel(),
            'source_url'       => $this->source_url,
            'embed_src'        => $this->embedSrc(),
            'thumbnail'        => $this->posterUrl(),
            'orientation'      => $this->isVertical() ? 'vertical' : 'horizontal',
            'status'           => $this->status,
            'status_label'     => self::ESTADOS[$this->status] ?? $this->status,
            'rejection_reason' => $this->status === 'rejected' ? $this->rejection_reason : null,
            'is_featured'      => (bool) $this->is_featured,
            'updated_at'       => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
