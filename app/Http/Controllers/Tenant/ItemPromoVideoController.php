<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\System\Addon;
use App\Models\System\Client;
use App\Models\System\ClientAddon;
use App\Models\System\PromoVideo;
use App\Models\Tenant\Item;
use App\Services\FeatureGate;
use App\Services\Marketplace\HomeShowcase;
use App\Services\Marketplace\VideoLink;
use App\Services\Marketplace\VideoLinkException;
use App\Services\Marketplace\VideoLinkResolver;
use Hyn\Tenancy\Environment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * «Video del producto» en el formulario de Tienda virtual (2026-11-19).
 *
 * La tienda pega el enlace de un video ya publicado en TikTok, YouTube o
 * Instagram (Reels). Queda «Por aprobar» en la bandeja del SuperAdmin
 * (/promo-videos) y, aprobado, sale en el marketplace. Un video por
 * producto. Solo con el extra `promo_videos` vigente (FeatureGate).
 *
 * Las filas viven en la base CENTRAL (`promo_videos`, por client_id +
 * remote_item_id), igual que los videos de la fase 1.
 */
class ItemPromoVideoController extends Controller
{
    public function __construct(private readonly VideoLinkResolver $resolver) {}

    /** Estado de la sección: si la tienda tiene el extra y el video actual. */
    public function show(int $item): JsonResponse
    {
        $itemModel = Item::findOrFail($item);
        $client = $this->client();
        $enabled = $client && app(FeatureGate::class)->has(Addon::PROMO_VIDEOS);

        $video = $client ? $this->videoOf($client, $itemModel->id) : null;

        return response()->json([
            'enabled' => $enabled,
            'video'   => $video?->toPanelArray(),
            'quota'   => $enabled ? $this->quotaInfo($client) : null,
        ]);
    }

    /** Vista previa sin guardar: valida el enlace y devuelve el reproductor. */
    public function preview(Request $request, int $item): JsonResponse
    {
        Item::findOrFail($item);
        if (!$this->client() || !app(FeatureGate::class)->has(Addon::PROMO_VIDEOS)) {
            return $this->notEnabled();
        }
        $request->validate(['url' => 'required|string|max:2000']);

        try {
            $link = $this->resolver->resolve($request->input('url'));
        } catch (VideoLinkException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        $meta = $this->resolver->metadata($link);

        return response()->json([
            'success' => true,
            'preview' => [
                'provider'       => $link->provider,
                'provider_label' => $link->label(),
                'source_url'     => $link->url,
                'embed_src'      => $link->embed(),
                'thumbnail'      => $meta['thumbnail'],
                'title'          => $meta['title'],
                'orientation'    => $link->orientation,
            ],
        ]);
    }

    /** Guarda (o cambia) el video del producto: queda por aprobar. */
    public function store(Request $request, int $item): JsonResponse
    {
        $itemModel = Item::findOrFail($item);
        $client = $this->client();
        if (!$client || !app(FeatureGate::class)->has(Addon::PROMO_VIDEOS)) {
            return $this->notEnabled();
        }
        $request->validate(['url' => 'required|string|max:2000']);

        try {
            $link = $this->resolver->resolve($request->input('url'));
        } catch (VideoLinkException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $video = $this->videoOf($client, $itemModel->id);

        // Mismo video que ya estaba: no se vuelve a mandar a revisión.
        if ($video && $video->isLink() && $video->provider === $link->provider
            && $video->provider_video_id === $link->id) {
            return response()->json([
                'success' => true,
                'message' => 'Es el mismo video que ya tenías.',
                'video'   => $video->toPanelArray(),
            ]);
        }

        // Cupo del extra: videos activos (no rechazados) de la tienda, sin
        // contar el de este producto, que se reemplaza.
        $quota = $this->quotaInfo($client, $itemModel->id);
        if (!$video && $quota['limit'] !== null && $quota['used'] >= $quota['limit']) {
            return response()->json([
                'success' => false,
                'message' => ($quota['limit'] === 1 ? 'Ya usas el único video de tu extra.' : "Ya usas los {$quota['limit']} videos de tu extra.")
                    . ' Quita el video de otro producto o pide más cupo a ebaemy.',
            ], 422);
        }

        $meta = $this->resolver->metadata($link);
        $thumb = $link->provider === 'youtube'
            ? $meta['thumbnail']
            : $this->resolver->storeThumbnail($link, $meta['thumbnail']);

        $old = $video?->thumbnail_url;
        $video ??= new PromoVideo([
            'client_id'      => $client->id,
            'remote_item_id' => $itemModel->id,
        ]);

        $video->fill([
            'hostname_id'       => $client->hostname_id,
            'item_title'        => mb_substr((string) ($itemModel->description ?: $itemModel->name ?: 'Producto'), 0, 250),
            'kind'              => 'link',
            'provider'          => $link->provider,
            'provider_video_id' => $link->id,
            'source_url'        => $link->url,
            'source_title'      => $meta['title'],
            // playback_url = el src del reproductor: es lo que `publico()` y
            // el sitemap esperan. Se arma desde el id, nunca desde la entrada.
            'playback_url'      => $link->embed(),
            'mp4_url'           => null,
            'thumbnail_url'     => $thumb,
            'aspect'            => $link->orientation,
            'duration_seconds'  => null,
            'width'             => null,
            'height'            => null,
            'status'            => 'pending_review',
            'rejection_reason'  => null,
            'reviewed_by'       => null,
            'reviewed_at'       => null,
            'published_at'      => null,
            'is_featured'       => false,
            'featured_until'    => null,
        ]);
        $video->save();

        if ($old && $old !== $thumb) {
            $this->resolver->deleteThumbnail($old);
        }
        HomeShowcase::forgetVideos();

        return response()->json([
            'success' => true,
            'message' => 'Video enviado. Lo revisamos y te avisamos aquí: cuando esté aprobado saldrá en el marketplace.',
            'video'   => $video->toPanelArray(),
        ]);
    }

    /** Quita el video del producto (deja de verse al momento). */
    public function destroy(int $item): JsonResponse
    {
        $itemModel = Item::findOrFail($item);
        $client = $this->client();
        $video = $client ? $this->videoOf($client, $itemModel->id) : null;
        if (!$video) {
            return response()->json(['success' => true, 'message' => 'Este producto no tenía video.']);
        }

        $this->resolver->deleteThumbnail($video->thumbnail_url);
        $video->delete();
        HomeShowcase::forgetVideos();

        return response()->json(['success' => true, 'message' => 'Video quitado.']);
    }

    // ── Internos ─────────────────────────────────────────────────────────

    private function client(): ?Client
    {
        $hostnameId = optional(app(Environment::class)->hostname())->id;
        return $hostnameId ? Client::where('hostname_id', $hostnameId)->first() : null;
    }

    private function videoOf(Client $client, int $itemId): ?PromoVideo
    {
        return PromoVideo::where('client_id', $client->id)->where('remote_item_id', $itemId)->first();
    }

    /** @return array{limit:?int, used:int} */
    private function quotaInfo(Client $client, ?int $exceptItemId = null): array
    {
        $limit = ClientAddon::query()->vigente()
            ->where('client_id', $client->id)
            ->where('addon_key', Addon::PROMO_VIDEOS)
            ->value('quota');

        $used = PromoVideo::where('client_id', $client->id)
            ->whereNotIn('status', ['rejected', 'failed'])
            ->when($exceptItemId, fn ($q) => $q->where('remote_item_id', '!=', $exceptItemId))
            ->count();

        return ['limit' => $limit !== null ? (int) $limit : null, 'used' => $used];
    }

    private function notEnabled(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'El video del producto es un extra. Pídelo al equipo de ebaemy para activarlo en tu tienda.',
        ], 403);
    }
}
