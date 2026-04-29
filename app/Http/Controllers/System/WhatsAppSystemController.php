<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\Client;
use App\Models\System\Configuration as SystemConfiguration;
use App\Models\System\SystemWhatsAppLog;
use App\Services\System\WhatsAppSystemService;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SuperAdmin → módulo WhatsApp.
 *
 * Permite al admin del SaaS:
 *   - Configurar el gateway WhatsApp del SISTEMA (qr_api_url + token)
 *   - Enviar un mensaje de prueba
 *   - Notificar a un tenant individual (recordatorios, anuncios, soporte)
 *   - Consultar el historial de envíos
 *
 * NO usa WhatsAppService del tenant (ese resuelve driver por hostname).
 */
class WhatsAppSystemController extends Controller
{
    public function __construct(private WhatsAppSystemService $service) {}

    public function index()
    {
        return view('system.whatsapp.index');
    }

    public function data(): JsonResponse
    {
        $config = SystemConfiguration::first();

        return response()->json([
            'configured' => $this->service->isConfigured(),
            'config' => [
                'qr_api_url'   => $config->qr_api_url ?? null,
                'has_token'    => !empty($config->qr_api_token),
                'qr_api_msg'   => $config->qr_api_msg ?? null,
            ],
            'stats' => [
                'today_sent'   => SystemWhatsAppLog::whereDate('created_at', today())->where('status', 'sent')->count(),
                'today_failed' => SystemWhatsAppLog::whereDate('created_at', today())->where('status', 'failed')->count(),
                'total'        => SystemWhatsAppLog::count(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_api_url'   => 'nullable|url|max:255',
            'qr_api_token' => 'nullable|string|max:500',
            'qr_api_msg'   => 'nullable|string|max:1000',
        ]);

        $config = SystemConfiguration::first();
        if (!$config) {
            return response()->json(['success' => false, 'message' => 'Configuración no inicializada'], 422);
        }

        if (array_key_exists('qr_api_url', $validated)) {
            $config->qr_api_url = $validated['qr_api_url'];
        }
        // Solo actualiza el token si vino un valor no vacío (permite conservar el actual)
        if (!empty($validated['qr_api_token'])) {
            $config->qr_api_token = $validated['qr_api_token'];
        }
        if (array_key_exists('qr_api_msg', $validated)) {
            $config->qr_api_msg = $validated['qr_api_msg'];
        }
        $config->save();

        return response()->json([
            'success' => true,
            'message' => 'Configuración actualizada',
        ]);
    }

    public function test(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone'   => 'required|string|max:32',
            'message' => 'required|string|max:1000',
        ]);

        $ok = $this->service->send(
            phone: $validated['phone'],
            message: $validated['message'],
            tenantHostnameId: null,
            source: 'manual'
        );

        return response()->json([
            'success' => $ok,
            'message' => $ok ? 'Mensaje enviado' : 'Falló el envío — revisa el log',
        ]);
    }

    /**
     * Listar tenants disponibles para el dropdown del form "Notificar tenant".
     */
    public function tenants(): JsonResponse
    {
        $tenants = Hostname::with('website')
            ->whereHas('website')
            ->orderBy('fqdn')
            ->get(['id', 'fqdn'])
            ->map(function ($h) {
                $client = Client::where('hostname_id', $h->id)->first();
                return [
                    'id'           => $h->id,
                    'fqdn'         => $h->fqdn,
                    'client_name'  => $client?->name,
                    'client_phone' => $client?->number,
                ];
            });

        return response()->json($tenants);
    }

    /**
     * Notificar a un tenant específico (envía al teléfono del cliente owner).
     */
    public function notifyTenant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hostname_id' => 'required|integer|exists:hostnames,id',
            'phone'       => 'required|string|max:32',
            'message'     => 'required|string|max:1000',
        ]);

        $hostname = Hostname::find($validated['hostname_id']);
        $client   = Client::where('hostname_id', $hostname->id)->first();

        $ok = $this->service->send(
            phone: $validated['phone'],
            message: $validated['message'],
            tenantHostnameId: $hostname->id,
            recipientName: $client?->name,
            source: 'tenant_notification'
        );

        return response()->json([
            'success' => $ok,
            'message' => $ok
                ? "Notificación enviada a {$hostname->fqdn}"
                : 'Falló el envío — revisa el log',
        ]);
    }

    public function logs(Request $request): JsonResponse
    {
        $logs = SystemWhatsAppLog::query()
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json($logs);
    }
}
