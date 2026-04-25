<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\MarketingCampaign;
use App\Models\System\MarketingContact;
use App\Services\System\MarketingCampaignService;
use Illuminate\Http\Request;

/**
 * Panel SuperAdmin para campañas de marketing centralizadas.
 * Bloque mínimo: listar, crear, agregar contactos, materializar targets,
 * disparar envío en lotes manualmente. Worker async = Fase 3.
 */
class MarketingCampaignController extends Controller
{
    public function __construct(private MarketingCampaignService $service) {}

    public function index()
    {
        $campaigns = MarketingCampaign::query()
            ->orderByDesc('created_at')
            ->paginate(20);

        $stats = [
            'contacts'        => MarketingContact::count(),
            'consented'       => MarketingContact::where('consent_marketing', true)->where('opted_out', false)->count(),
            'opted_out'       => MarketingContact::where('opted_out', true)->count(),
        ];

        return view('system.marketing.campaigns_index', compact('campaigns', 'stats'));
    }

    public function create()
    {
        return view('system.marketing.campaign_form', [
            'campaign' => new MarketingCampaign(['channel' => 'email', 'status' => 'draft']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:180',
            'channel' => 'required|in:whatsapp,email,sms',
            'subject' => 'nullable|string|max:200',
            'message' => 'required|string|max:4000',
            'segment_tags'        => 'nullable|string|max:500',
            'segment_hostname_id' => 'nullable|integer',
            'segment_source'      => 'nullable|string|max:64',
        ]);

        $segment = array_filter([
            'tags'        => isset($data['segment_tags']) ? array_filter(array_map('trim', explode(',', $data['segment_tags']))) : null,
            'hostname_id' => $data['segment_hostname_id'] ?? null,
            'source'      => $data['segment_source']      ?? null,
        ]);

        $campaign = MarketingCampaign::create([
            'name'       => $data['name'],
            'channel'    => $data['channel'],
            'subject'    => $data['subject'] ?? null,
            'message'    => $data['message'],
            'status'     => MarketingCampaign::STATUS_DRAFT,
            'segment'    => $segment ?: null,
            'created_by' => auth('admin')->id(),
        ]);

        return redirect()->route('system.marketing.campaigns.show', $campaign->id)
            ->with('ok', 'Campaña creada en estado borrador. Materializa targets y despáchala desde acá.');
    }

    public function show(int $id)
    {
        $campaign = MarketingCampaign::findOrFail($id);
        $sample = $campaign->targets()->with('contact')->latest()->limit(20)->get();

        return view('system.marketing.campaign_show', compact('campaign', 'sample'));
    }

    /**
     * Materializa los targets según el segmento (idempotente — usa
     * updateOrCreate). Tras esto, la campaña pasa de draft a scheduled.
     */
    public function buildTargets(int $id)
    {
        $campaign = MarketingCampaign::findOrFail($id);
        $count = $this->service->buildTargets($campaign);

        if ($campaign->status === MarketingCampaign::STATUS_DRAFT) {
            $campaign->update(['status' => MarketingCampaign::STATUS_SCHEDULED]);
        }

        return back()->with('ok', "Targets materializados: {$count} contactos.");
    }

    /**
     * Procesa hasta `batch` targets pending de forma SÍNCRONA en este request.
     * Usar para lotes pequeños o tests; para campañas grandes preferir
     * `dispatchAsync` que encola un job con auto-reencolado.
     */
    public function sendBatch(int $id, Request $request)
    {
        $campaign = MarketingCampaign::findOrFail($id);
        $batch = max(10, min(500, (int) $request->input('batch', 100)));

        $result = $this->service->process($campaign, $batch);

        return back()->with('ok',
            "Lote procesado: {$result['sent']} enviados, {$result['failed']} con error, {$result['skipped']} saltados (no consent / opted out)."
        );
    }

    /**
     * Encola un job que procesa la campaña en background. El job se
     * re-encola entre lotes hasta agotar los targets pending. Requiere
     * worker corriendo (`php artisan queue:work`).
     */
    public function sendAsync(int $id, Request $request)
    {
        $campaign = MarketingCampaign::findOrFail($id);
        $batch = max(10, min(500, (int) $request->input('batch', 100)));

        if ($campaign->status === MarketingCampaign::STATUS_DRAFT) {
            $campaign->update(['status' => MarketingCampaign::STATUS_SCHEDULED]);
        }

        \App\Jobs\ProcessMarketingCampaign::dispatch($campaign->id, $batch);

        return back()->with('ok',
            'Envío encolado en background. El job procesará lotes de ' . $batch . ' targets hasta agotar los pendientes.'
        );
    }
}
