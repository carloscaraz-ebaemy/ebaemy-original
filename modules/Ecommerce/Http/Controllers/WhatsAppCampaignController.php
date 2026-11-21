<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\WhatsAppOfferCampaign;
use App\Models\Tenant\WhatsAppOfferCampaignMessage;
use App\Services\Tenant\WhatsAppService;
use Illuminate\Http\Request;

class WhatsAppCampaignController extends Controller
{
    public function index()
    {
        return view('ecommerce::whatsapp_campaigns.index');
    }

    public function records()
    {
        $records = WhatsAppOfferCampaign::query()
            ->with(['flashSale:id,title'])
            ->withCount(['messages as pending_count' => fn ($q) => $q->where('status', 'pending')])
            ->addSelect(['next_scheduled_at' => WhatsAppOfferCampaignMessage::select('scheduled_at')
                ->whereColumn('campaign_id', 'whatsapp_offer_campaigns.id')
                ->where('status', 'pending')->whereNotNull('scheduled_at')
                ->orderBy('scheduled_at')->limit(1)])
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'status' => $row->status,
                    'flash_sale_id' => $row->flash_sale_id,
                    'flash_sale_title' => optional($row->flashSale)->title,
                    'kind' => $row->kind ?? 'flash_sale',
                    'audience' => data_get($row->meta, 'audience'),
                    'products' => collect(data_get($row->meta, 'products', []))->pluck('description')->take(5)->values(),
                    'pending_count' => (int) ($row->pending_count ?? 0),
                    'next_scheduled_at' => $row->next_scheduled_at,
                    'total_customers' => (int) $row->total_customers,
                    'sent_count' => (int) $row->sent_count,
                    'failed_count' => (int) $row->failed_count,
                    'started_at' => optional($row->started_at)->format('Y-m-d H:i:s'),
                    'finished_at' => optional($row->finished_at)->format('Y-m-d H:i:s'),
                    'created_at' => optional($row->created_at)->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json(['data' => $records]);
    }

    // ── Campañas por productos (2026-11-21) ────────────────────────────

    /** Cuántos clientes entrarían con cada audiencia, para decidir antes de crear. */
    public function audiences()
    {
        $svc = new \App\Services\Tenant\WhatsAppOfferCampaignService();
        $wa  = new WhatsAppService();
        $out = [];
        foreach (\App\Services\Tenant\WhatsAppOfferCampaignService::AUDIENCIAS as $a) {
            $out[$a] = $svc->clientesElegibles($a, 72)->count();
        }

        return response()->json([
            'audiences'      => $out,
            'whatsapp_ready' => $wa->isEnabled() && !$wa->downReason(),
            'whatsapp_issue' => $wa->isEnabled() ? $wa->downReason() : 'WhatsApp no está conectado en esta tienda.',
            'defaults'       => [
                'spacing_seconds' => \App\Services\Tenant\WhatsAppOfferCampaignService::ESPACIADO_DEFECTO,
                'daily_cap'       => \App\Services\Tenant\WhatsAppOfferCampaignService::TOPE_DIARIO_DEFECTO,
            ],
        ]);
    }

    /** Texto tal como lo verá el primer cliente, sin enviar nada. */
    public function preview(Request $request)
    {
        $data = $request->validate([
            'items'    => 'required|array|min:1|max:5',
            'items.*'  => 'integer',
            'message'  => 'nullable|string|max:600',
            'audience' => 'nullable|string|in:all,buyers_12m,buyers_3m',
        ]);

        $svc = new \App\Services\Tenant\WhatsAppOfferCampaignService();
        $products = $svc->productosParaCampana($data['items'], url('/ecommerce'));
        if ($products->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Ninguno de los productos elegidos está en la tienda virtual con stock.'], 422);
        }
        $cliente = $svc->clientesElegibles($data['audience'] ?? 'all', 72)->first(['id', 'name']);
        $storeName = (string) (optional(\App\Models\Tenant\Company::query()->first())->trade_name ?: 'nuestra tienda');

        return response()->json([
            'success'  => true,
            'text'     => $svc->textoCampana($cliente?->name ?: 'Cliente', (string) ($data['message'] ?? ''), $products, $storeName),
            'products' => $products->count(),
            'customers' => $svc->clientesElegibles($data['audience'] ?? 'all', 72)->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'nullable|string|max:120',
            'items'           => 'required|array|min:1|max:5',
            'items.*'         => 'integer',
            'message'         => 'nullable|string|max:600',
            'audience'        => 'nullable|string|in:all,buyers_12m,buyers_3m',
            'spacing_seconds' => 'nullable|integer|min:20|max:600',
            'daily_cap'       => 'nullable|integer|min:10|max:300',
        ]);

        $result = (new \App\Services\Tenant\WhatsAppOfferCampaignService())->crearPorProductos($data['items'], [
            'campaign_name'      => $data['name'] ?? null,
            'message'            => $data['message'] ?? '',
            'audience'           => $data['audience'] ?? 'all',
            'spacing_seconds'    => $data['spacing_seconds'] ?? null,
            'daily_cap'          => $data['daily_cap'] ?? null,
            'base_ecommerce_url' => url('/ecommerce'),
        ]);

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function cancel($id)
    {
        $campaign = WhatsAppOfferCampaign::findOrFail($id);
        if (!in_array($campaign->status, ['queued', 'processing'], true)) {
            return response()->json(['success' => false, 'message' => 'Esa campaña ya terminó.'], 422);
        }
        $n = (new \App\Services\Tenant\WhatsAppOfferCampaignService())->cancelar($campaign);

        return response()->json(['success' => true, 'message' => "Campaña detenida. {$n} mensajes no se enviarán."]);
    }

    /** El cliente respondió BAJA: no vuelve a entrar en ninguna campaña. */
    public function optOut(Request $request, $personId)
    {
        $person = \App\Models\Tenant\Person::findOrFail($personId);
        $person->whatsapp_opt_out = (bool) $request->input('opt_out', true);
        $person->save();

        return response()->json(['success' => true, 'opt_out' => (bool) $person->whatsapp_opt_out]);
    }

    public function messages(Request $request, $id)
    {
        $campaign = WhatsAppOfferCampaign::findOrFail($id);

        $status = $request->input('status');
        $search = trim((string) $request->input('search'));
        $perPage = max(10, min((int) $request->input('per_page', 20), 100));

        $query = $campaign->messages()
            ->with(['person:id,name,telephone,whatsapp_opt_out'])
            ->orderByDesc('id');

        if ($status) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'like', "%{$search}%")
                    ->orWhereHas('person', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")
                            ->orWhere('telephone', 'like', "%{$search}%");
                    });
            });
        }

        $page = $query->paginate($perPage);

        $data = collect($page->items())->map(function ($row) {
            return [
                'id' => $row->id,
                'person_id' => $row->person_id,
                'customer_name' => optional($row->person)->name ?: '-',
                'customer_phone' => $row->phone ?: optional($row->person)->telephone,
                'status' => $row->status,
                'error_message' => $row->error_message,
                'sent_at' => optional($row->sent_at)->format('Y-m-d H:i:s'),
                'scheduled_at' => optional($row->scheduled_at)->format('Y-m-d H:i:s'),
                'opt_out' => (bool) optional($row->person)->whatsapp_opt_out,
                'created_at' => optional($row->created_at)->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function retryFailed($id)
    {
        $campaign = WhatsAppOfferCampaign::with('messages.person')->findOrFail($id);

        $wa = new WhatsAppService();
        if (!$wa->isEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'WhatsApp no esta configurado para este tenant.',
            ], 422);
        }

        $rows = $campaign->messages()
            ->with('person:id,name,telephone')
            ->whereIn('status', ['failed', 'pending'])
            ->get();

        $retried = 0;
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $phone = $row->phone ?: optional($row->person)->telephone;
            if (!$phone) {
                $row->update([
                    'status' => 'skipped',
                    'error_message' => 'Cliente sin telefono para WhatsApp.',
                ]);
                $skipped++;
                continue;
            }

            $message = $this->messageFromPayload($row->payload, optional($row->person)->name);

            $retried++;
            $ok = $wa->send((string) $phone, $message);

            if ($ok) {
                $row->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'error_message' => null,
                ]);
                $sent++;
            } else {
                $row->update([
                    'status' => 'failed',
                    'error_message' => 'Error del proveedor WhatsApp.',
                ]);
                $failed++;
            }
        }

        $campaign->update([
            'status' => 'completed',
            'sent_count' => $campaign->messages()->where('status', 'sent')->count(),
            'failed_count' => $campaign->messages()->where('status', 'failed')->count(),
            'finished_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reintento ejecutado.',
            'retried' => $retried,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Extrae el texto del mensaje desde el payload original de la campaña.
     * El payload puede venir como:
     *   - array con ['text' => '...']                → usar text
     *   - array con ['subject' => ..., 'body' => ...]  → concatenar
     *   - string JSON                                → decodificar primero
     *   - null / cualquier otra cosa                 → caer al greeting default
     *
     * Siempre valida el resultado final para evitar enviar WhatsApp con
     * payload malformado o con strings demasiado largos (>4000 chars).
     */
    private function messageFromPayload($payload, ?string $customerName): string
    {
        // Normalizar a array — soporta string JSON (DB) y objeto (casts legacy)
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : null;
        } elseif (is_object($payload)) {
            $payload = (array) $payload;
        }

        $name = trim((string) $customerName);
        if ($name !== '' && str_contains($name, ' ')) {
            $name = explode(' ', $name)[0];
        }
        $greeting = $name !== '' ? "Hola {$name}" : 'Hola';

        if (is_array($payload)) {
            // Texto explícito
            if (!empty($payload['text']) && is_string($payload['text'])) {
                return $this->clampMessage($payload['text']);
            }

            // Subject + body (plantillas viejas)
            $parts = [];
            if (!empty($payload['subject']))   $parts[] = (string) $payload['subject'];
            if (!empty($payload['body']))      $parts[] = (string) $payload['body'];
            if (!empty($parts)) {
                return $this->clampMessage(implode("\n\n", $parts));
            }

            // Lista de productos simple (payload de flash sale)
            if (!empty($payload['products']) && is_array($payload['products'])) {
                $lines = [$greeting . ", tenemos estas ofertas para ti:"];
                foreach (array_slice($payload['products'], 0, 5) as $p) {
                    $p = (array) $p;
                    if (!empty($p['name'])) {
                        $price = isset($p['price']) ? ' — S/ ' . number_format((float) $p['price'], 2) : '';
                        $lines[] = '• ' . $p['name'] . $price;
                    }
                }
                return $this->clampMessage(implode("\n", $lines));
            }
        }

        return $this->clampMessage($greeting . ", tenemos nuevas ofertas para ti.\nEscribenos y te ayudamos con tu pedido.");
    }

    /**
     * Limita el mensaje a 4000 chars (límite WhatsApp Business API) y quita
     * caracteres de control. Previene que un payload corrupto de BD reviente
     * la llamada al proveedor.
     */
    private function clampMessage(string $text): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        return mb_substr(trim($text), 0, 4000);
    }
}

