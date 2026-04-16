<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\WhatsAppOfferCampaign;
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

    public function messages(Request $request, $id)
    {
        $campaign = WhatsAppOfferCampaign::findOrFail($id);

        $status = $request->input('status');
        $search = trim((string) $request->input('search'));
        $perPage = max(10, min((int) $request->input('per_page', 20), 100));

        $query = $campaign->messages()
            ->with(['person:id,name,telephone'])
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

    private function messageFromPayload($payload, ?string $customerName): string
    {
        if (is_array($payload) && !empty($payload['text'])) {
            return (string) $payload['text'];
        }

        $name = trim((string) $customerName);
        if ($name !== '' && str_contains($name, ' ')) {
            $name = explode(' ', $name)[0];
        }
        $greeting = $name !== '' ? "Hola {$name}" : 'Hola';

        return $greeting . ", tenemos nuevas ofertas para ti.\nEscribenos y te ayudamos con tu pedido.";
    }
}

