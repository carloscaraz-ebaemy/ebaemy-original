<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Video promocional de UN producto de una tienda. Se publica solo con la
 * aprobación del SuperAdmin y mientras la tienda tenga el extra vigente.
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
    public function schemaOrg(string $nombreProducto, ?string $urlFicha = null): array
    {
        return array_filter([
            '@type'        => 'VideoObject',
            'name'         => $this->title ?: $nombreProducto,
            'description'  => $this->description ?: ('Video de ' . $nombreProducto),
            'thumbnailUrl' => $this->thumbnail_url,
            'uploadDate'   => optional($this->published_at ?? $this->created_at)->toIso8601String(),
            'duration'     => 'PT' . (int) $this->duration_seconds . 'S',
            'contentUrl'   => $this->mp4_url,
            'embedUrl'     => $this->playback_url,
            'url'          => $urlFicha,
        ]);
    }
}
