<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\Addon;
use App\Models\System\Client;
use App\Models\System\ClientAddon;
use App\Models\System\PromoVideo;
use App\Services\FeatureGate;
use App\Services\Marketplace\HomeShowcase;
use Illuminate\Http\Request;

/**
 * SuperAdmin → Videos promocionales (/promo-videos).
 *
 * Tres cosas en una pantalla:
 *  - el precio y cupo por defecto del extra,
 *  - qué tiendas lo tienen, a qué precio, con qué cupo y hasta cuándo pagado,
 *  - la bandeja: cada video espera aprobación antes de publicarse (decidido
 *    2026-11-17), y aquí se destaca para las páginas de ebaemy.
 */
class PromoVideoController extends Controller
{
    public function index(Request $request)
    {
        $addon = Addon::where('key', Addon::PROMO_VIDEOS)->firstOrFail();

        $contratos = ClientAddon::where('addon_key', Addon::PROMO_VIDEOS)->get()->keyBy('client_id');
        $usados = PromoVideo::query()
            ->whereNotIn('status', ['rejected', 'failed'])
            ->selectRaw('client_id, COUNT(*) AS n')
            ->groupBy('client_id')
            ->pluck('n', 'client_id');

        $tiendas = Client::with('hostname')->orderBy('name')->get()->map(fn ($c) => (object) [
            'id'       => $c->id,
            'nombre'   => $c->name,
            'fqdn'     => $c->hostname->fqdn ?? null,
            'contrato' => $contratos->get($c->id),
            'usados'   => (int) ($usados[$c->id] ?? 0),
        ]);

        $tab = in_array($request->input('tab'), ['tiendas', 'bandeja', 'publicados'], true)
            ? $request->input('tab') : 'bandeja';

        $videos = PromoVideo::with('client')
            ->when($tab === 'bandeja', fn ($q) => $q->where('status', 'pending_review')->oldest())
            ->when($tab === 'publicados', fn ($q) => $q->whereIn('status', ['approved', 'hidden'])->latest('published_at'))
            ->when($tab === 'tiendas', fn ($q) => $q->whereRaw('1 = 0'))
            ->limit(100)
            ->get();

        return view('system.promo_videos.index', [
            'addon'      => $addon,
            'tiendas'    => $tiendas,
            'videos'     => $videos,
            'tab'        => $tab,
            'porAprobar' => PromoVideo::where('status', 'pending_review')->count(),
        ]);
    }

    public function saveDefaults(Request $request)
    {
        $data = $request->validate([
            'default_monthly_price' => 'required|numeric|min:0|max:99999',
            'default_quota'         => 'nullable|integer|min:1|max:10000',
        ]);

        Addon::where('key', Addon::PROMO_VIDEOS)->update($data);

        return back()->with('pv_ok', 'Precio y cupo por defecto guardados. Se aplican a las tiendas que actives desde ahora.');
    }

    /** Activa o edita el extra de una tienda. */
    public function saveClient(Request $request, Client $client)
    {
        $data = $request->validate([
            'status'        => 'required|in:active,suspended,cancelled',
            'monthly_price' => 'required|numeric|min:0|max:99999',
            'quota'         => 'nullable|integer|min:1|max:10000',
            'paid_until'    => 'nullable|date',
            'notes'         => 'nullable|string|max:500',
        ]);

        $contrato = ClientAddon::firstOrNew(['client_id' => $client->id, 'addon_key' => Addon::PROMO_VIDEOS]);
        if (!$contrato->exists) {
            $contrato->starts_at  = now()->toDateString();
            $contrato->enabled_by = auth('admin')->id();
        }
        $contrato->fill($data)->save();

        FeatureGate::flushHostname((int) $client->hostname_id);
        HomeShowcase::forgetVideos();

        return back()->with('pv_ok', "Videos promocionales de {$client->name}: guardado.");
    }

    /** Un mes pagado más, desde el último día pagado o desde hoy si ya venció. */
    public function registerPayment(Client $client)
    {
        $contrato = ClientAddon::where('client_id', $client->id)
            ->where('addon_key', Addon::PROMO_VIDEOS)->firstOrFail();
        $contrato->registrarMesPagado();

        FeatureGate::flushHostname((int) $client->hostname_id);
        HomeShowcase::forgetVideos();

        return back()->with('pv_ok', "{$client->name}: pagado hasta el " . $contrato->paid_until->format('d/m/Y') . '.');
    }

    /** Aprobar, rechazar, ocultar, volver a publicar o destacar un video. */
    public function review(Request $request, PromoVideo $video)
    {
        $data = $request->validate([
            'action'         => 'required|in:approve,reject,hide,feature,unfeature',
            'reason'         => 'required_if:action,reject|nullable|string|max:300',
            'featured_until' => 'nullable|date|after_or_equal:today',
        ]);

        switch ($data['action']) {
            case 'approve':
                if (!in_array($video->status, ['pending_review', 'hidden', 'rejected'], true)) {
                    return back()->with('pv_error', 'Solo se aprueba un video ya procesado.');
                }
                $video->status = 'approved';
                $video->rejection_reason = null;
                $video->published_at ??= now();
                break;
            case 'reject':
                $video->status = 'rejected';
                $video->rejection_reason = $data['reason'];
                $video->is_featured = false;
                break;
            case 'hide':
                $video->status = 'hidden';
                $video->is_featured = false;
                break;
            case 'feature':
                if ($video->status !== 'approved') {
                    return back()->with('pv_error', 'Solo se destaca un video publicado.');
                }
                $video->is_featured = true;
                $video->featured_until = $data['featured_until'] ?? null;
                break;
            case 'unfeature':
                $video->is_featured = false;
                $video->featured_until = null;
                break;
        }

        $video->reviewed_by = auth('admin')->id();
        $video->reviewed_at = now();
        $video->save();
        HomeShowcase::forgetVideos();

        return back()->with('pv_ok', 'Video de «' . ($video->item_title ?: 'producto') . '»: ' . strtolower(PromoVideo::ESTADOS[$video->status] ?? $video->status)
            . ($video->is_featured ? ', destacado' : '') . '.');
    }
}
