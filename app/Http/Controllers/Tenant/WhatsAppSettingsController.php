<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ConfigurationEcommerce;
use App\Models\Tenant\WhatsAppMessageLog;
use App\Services\Tenant\WhatsApp\WhatsAppDriverFactory;
use App\Services\Tenant\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Panel de configuración WhatsApp (fase 2).
 *
 * Permite al admin del tenant:
 *   - Ver driver activo y estado de configuración
 *   - Configurar credenciales (Meta Cloud: token + phone_id)
 *   - Configurar QR API (URL + apiKey)
 *   - Elegir driver preferido
 *   - Activar/desactivar notificaciones por tipo de evento
 *   - Enviar mensaje de prueba a un número
 *   - Ver plantillas aprobadas en Meta (solo meta_cloud)
 */
class WhatsAppSettingsController extends Controller
{
    /** Tipos de notificación soportados por el sistema */
    public const NOTIFICATION_TYPES = [
        'order_created'    => 'Pedido recibido (al cliente)',
        'admin_new_order'  => 'Nuevo pedido (al admin)',
        'payment_verified' => 'Pago verificado',
        'order_dispatched' => 'Pedido despachado',
        'order_delivered'  => 'Pedido entregado',
        'order_cancelled'  => 'Pedido cancelado',
        'abandoned_cart'   => 'Carrito abandonado',
    ];

    public function index()
    {
        return view('tenant.whatsapp_settings.index');
    }

    /**
     * GET /whatsapp/settings/data
     * Retorna config actual + drivers disponibles + último log.
     */
    public function data()
    {
        $config = ConfigurationEcommerce::first();
        $drivers = WhatsAppDriverFactory::availableDrivers();
        $activeDriver = WhatsAppDriverFactory::make();

        // Config legacy (QR API) también se muestra
        $legacyConfig = \App\Models\Tenant\Configuration::first();

        $notifications = $config->whatsapp_notifications_enabled ?? $this->defaultNotifications();

        // Asegurar que todas las keys existan
        foreach (array_keys(self::NOTIFICATION_TYPES) as $key) {
            if (!array_key_exists($key, $notifications)) {
                $notifications[$key] = true;
            }
        }

        return response()->json([
            'active_driver'   => $activeDriver->name(),
            'active_configured' => $activeDriver->isConfigured(),
            'drivers'         => $drivers,
            'notification_types' => self::NOTIFICATION_TYPES,
            'config' => [
                // Meta Cloud
                'whatsapp_driver'          => $config->whatsapp_driver,
                'whatsapp_api_token'       => $this->maskToken($config->whatsapp_api_token),
                'whatsapp_api_token_set'   => !empty($config->whatsapp_api_token),
                'whatsapp_phone_id'        => $config->whatsapp_phone_id,
                'whatsapp_phone_number_id' => $config->whatsapp_phone_number_id ?? null,
                'whatsapp_vendor_number'   => $config->whatsapp_vendor_number,
                'notifications'            => $notifications,
                // QR API (legacy)
                'qr_api_enable' => (bool) ($legacyConfig->qr_api_enable ?? false),
                'qr_api_url'    => $legacyConfig->qr_api_url ?? null,
                'qr_api_apiKey_set' => !empty($legacyConfig->qr_api_apiKey ?? null),
            ],
            'stats_today' => $this->statsToday(),
        ]);
    }

    /**
     * PUT /whatsapp/settings
     * Guarda la configuración.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'whatsapp_driver'          => 'nullable|in:meta_cloud,qr_api,none',
            'whatsapp_api_token'       => 'nullable|string|max:500',
            'whatsapp_phone_id'        => 'nullable|string|max:50',
            'whatsapp_vendor_number'   => 'nullable|string|max:30',
            'notifications'            => 'nullable|array',
            'notifications.*'          => 'boolean',
            'qr_api_enable'            => 'nullable|boolean',
            'qr_api_url'               => 'nullable|url|max:255',
            'qr_api_apiKey'            => 'nullable|string|max:255',
        ]);

        // Meta Cloud + preferencias
        $ecomConfig = ConfigurationEcommerce::firstOrCreate(['id' => 1]);
        $ecomConfig->fill([
            'whatsapp_driver'          => $validated['whatsapp_driver'] ?? $ecomConfig->whatsapp_driver,
            'whatsapp_phone_id'        => $validated['whatsapp_phone_id'] ?? $ecomConfig->whatsapp_phone_id,
            'whatsapp_vendor_number'   => $validated['whatsapp_vendor_number'] ?? $ecomConfig->whatsapp_vendor_number,
        ]);

        // Token solo se actualiza si viene explícito (para no borrarlo al dejar el campo vacío)
        if ($request->filled('whatsapp_api_token')) {
            $ecomConfig->whatsapp_api_token = $validated['whatsapp_api_token'];
        }

        if (isset($validated['notifications'])) {
            $ecomConfig->whatsapp_notifications_enabled = array_merge(
                $this->defaultNotifications(),
                $validated['notifications']
            );
        }

        $ecomConfig->save();

        // QR API legacy
        $legacyConfig = \App\Models\Tenant\Configuration::firstOrCreate(['id' => 1]);
        if (isset($validated['qr_api_enable'])) {
            $legacyConfig->qr_api_enable = (bool) $validated['qr_api_enable'];
        }
        if (array_key_exists('qr_api_url', $validated)) {
            $legacyConfig->qr_api_url = $validated['qr_api_url'];
        }
        if ($request->filled('qr_api_apiKey')) {
            $legacyConfig->qr_api_apiKey = $validated['qr_api_apiKey'];
        }
        $legacyConfig->save();

        // Refrescar cache si existe
        if (method_exists($ecomConfig, 'forgetCache')) {
            $ecomConfig->forgetCache();
        }

        return response()->json([
            'message'       => 'Configuración de WhatsApp actualizada',
            'active_driver' => WhatsAppDriverFactory::make()->name(),
        ]);
    }

    /**
     * POST /whatsapp/settings/test
     * Envía un mensaje de prueba.
     */
    public function test(Request $request)
    {
        $validated = $request->validate([
            'phone'    => 'required|string|max:30',
            'message'  => 'nullable|string|max:500',
        ]);

        $message = $validated['message'] ?? 'Mensaje de prueba desde el panel ebaemy. Si ves este mensaje, tu integración WhatsApp funciona correctamente. ✅';

        /** @var WhatsAppService $wa */
        $wa = app(WhatsAppService::class);

        if (!$wa->isEnabled()) {
            return response()->json([
                'success'      => false,
                'message'      => 'No hay driver de WhatsApp configurado. Completa las credenciales primero.',
                'driver'       => $wa->driverName(),
            ], 422);
        }

        $ok = $wa->send($validated['phone'], $message, 'manual_test');

        return response()->json([
            'success' => $ok,
            'driver'  => $wa->driverName(),
            'message' => $ok
                ? "Mensaje enviado vía {$wa->driverName()}. Revisa WhatsApp en el número indicado."
                : "No se pudo enviar. Revisa el log de mensajes para ver el error.",
        ], $ok ? 200 : 422);
    }

    /**
     * GET /whatsapp/settings/templates
     * Lista las plantillas aprobadas en Meta Business Account.
     * Solo funciona con driver meta_cloud.
     */
    public function templates()
    {
        $config = ConfigurationEcommerce::first();
        $token = $config->whatsapp_api_token ?? null;
        $phoneId = $config->whatsapp_phone_id ?? null;

        if (!$token || !$phoneId) {
            return response()->json([
                'templates' => [],
                'error'     => 'Meta Cloud no configurado',
            ], 422);
        }

        // Obtener WABA ID desde el Phone Number
        try {
            $phoneResp = Http::withToken($token)
                ->timeout(15)
                ->get("https://graph.facebook.com/v18.0/{$phoneId}", ['fields' => 'whatsapp_business_account_id']);

            if (!$phoneResp->successful()) {
                return response()->json([
                    'templates' => [],
                    'error'     => 'No se pudo obtener Business Account: ' . ($phoneResp->json('error.message') ?? 'error desconocido'),
                ], 422);
            }

            $wabaId = $phoneResp->json('whatsapp_business_account_id');
            if (!$wabaId) {
                return response()->json(['templates' => [], 'error' => 'Phone sin WABA asociado'], 422);
            }

            $tplResp = Http::withToken($token)
                ->timeout(15)
                ->get("https://graph.facebook.com/v18.0/{$wabaId}/message_templates", [
                    'limit' => 100,
                ]);

            if (!$tplResp->successful()) {
                return response()->json([
                    'templates' => [],
                    'error'     => 'Error al listar plantillas: ' . ($tplResp->json('error.message') ?? 'error'),
                ], 422);
            }

            $templates = collect($tplResp->json('data') ?? [])->map(function ($t) {
                return [
                    'name'     => $t['name'] ?? null,
                    'language' => $t['language'] ?? null,
                    'status'   => $t['status'] ?? null,
                    'category' => $t['category'] ?? null,
                    'body'     => collect($t['components'] ?? [])
                        ->firstWhere('type', 'BODY')['text'] ?? null,
                ];
            })->values();

            return response()->json(['templates' => $templates]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp templates fetch failed', ['error' => $e->getMessage()]);
            return response()->json([
                'templates' => [],
                'error'     => 'Excepción: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /whatsapp/settings/logs?limit=50&status=&driver=
     * Últimos logs de envío (para historial + debugging).
     */
    public function logs(Request $request)
    {
        $limit = min((int) $request->input('limit', 50), 200);

        $query = WhatsAppMessageLog::orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('driver')) {
            $query->where('driver', $request->input('driver'));
        }
        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . $request->input('phone') . '%');
        }

        return response()->json([
            'logs' => $query->limit($limit)->get()->map(fn($l) => [
                'id'         => $l->id,
                'phone'      => $l->phone,
                'driver'     => $l->driver,
                'type'       => $l->type,
                'template'   => $l->template_name,
                'message'    => mb_substr($l->message ?? '', 0, 120),
                'status'     => $l->status,
                'source'     => $l->source,
                'source_id'  => $l->source_id,
                'error'      => $l->error_message,
                'created_at' => $l->created_at?->format('Y-m-d H:i:s'),
            ]),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    protected function defaultNotifications(): array
    {
        return array_fill_keys(array_keys(self::NOTIFICATION_TYPES), true);
    }

    protected function maskToken(?string $token): ?string
    {
        if (!$token) return null;
        if (strlen($token) <= 8) return str_repeat('•', strlen($token));
        return substr($token, 0, 4) . str_repeat('•', 12) . substr($token, -4);
    }

    protected function statsToday(): array
    {
        try {
            $today = now()->startOfDay();
            return [
                'sent'   => WhatsAppMessageLog::sent()->where('created_at', '>=', $today)->count(),
                'failed' => WhatsAppMessageLog::failed()->where('created_at', '>=', $today)->count(),
            ];
        } catch (\Throwable) {
            return ['sent' => 0, 'failed' => 0];
        }
    }
}
