<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Configuration;
use App\Services\Tenant\WhatsApp\Evolution\EvolutionClient;
use Hyn\Tenancy\Environment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * El WhatsApp de la tienda conectado por QR (Evolution API), como en el Pro 9.
 *
 * La tienda escanea un QR con su celular y desde ahí el sistema envía con su
 * número: avisos automáticos (vía EvolutionDriver) y comprobantes en PDF
 * (enviarDocumento, el botón «Enviar» de nota de venta / boleta / factura).
 *
 * Una instancia por tienda, con nombre fijo derivado del tenant: volver a
 * conectar reutiliza la misma en vez de dejar huérfanas en el proveedor.
 */
class WhatsAppConnectionController extends Controller
{
    public function estado()
    {
        if (!$this->listo()) {
            return ['available' => false, 'connected' => false];
        }
        $c = Configuration::first();
        $conectado = ($c->qr_api_connection_state ?? null) === 'open' && $c->qr_api_instance;

        // Consulta en vivo solo si hay instancia: el número puede haberse
        // desvinculado desde el celular.
        if ($c->qr_api_instance) {
            try {
                $cli = new EvolutionClient();
                $r = $cli->state($c->qr_api_instance);
                $vivo = EvolutionClient::isConnected($r);
                if ($vivo && (!$conectado || !$c->qr_api_connected_phone)) {
                    $o = $cli->owner($c->qr_api_instance);
                    $c->qr_api_connected_phone = $o['phone'] ?: $c->qr_api_connected_phone;
                    $c->qr_api_profile_name = $o['name'] ?: $c->qr_api_profile_name;
                    $c->qr_api_connected_at = $c->qr_api_connected_at ?: now();
                }
                $c->qr_api_connection_state = $vivo ? 'open' : (data_get($r, 'instance.state') ?: data_get($r, 'state') ?: 'connecting');
                $c->save();
                $conectado = $vivo;
            } catch (\Throwable $e) {
                Log::warning('[WhatsAppQR] estado: ' . $e->getMessage());
            }
        }

        return [
            'available' => true,
            'connected' => (bool) $conectado,
            'state'     => $c->qr_api_connection_state,
            'phone'     => $c->qr_api_connected_phone,
            'name'      => $c->qr_api_profile_name,
            'since'     => $c->qr_api_connected_at ? (string) $c->qr_api_connected_at : null,
        ];
    }

    /** Crea (o reutiliza) la instancia y devuelve el QR para escanear. */
    public function conectar()
    {
        if (!$this->listo()) {
            return response()->json(['success' => false, 'message' => 'La conexión por QR no está disponible en el servidor.'], 422);
        }
        $c = Configuration::first();
        $instancia = $this->nombreInstancia();

        try {
            $cli = new EvolutionClient();
            $creada = $cli->createInstance($instancia);
            $qr = EvolutionClient::extractQr($creada) ?: EvolutionClient::extractQr($cli->qr($instancia));
        } catch (\Throwable $e) {
            Log::warning('[WhatsAppQR] conectar: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'No se pudo iniciar la conexión: ' . $e->getMessage()], 502);
        }

        $c->qr_api_instance = $instancia;
        $c->qr_api_connection_state = 'connecting';
        $c->qr_api_connected_phone = null;
        $c->qr_api_profile_name = null;
        $c->qr_api_connected_at = null;
        $c->save();

        return ['success' => true, 'qr' => $qr];
    }

    /** El QR caduca cada ~40 s: la pantalla lo vuelve a pedir. */
    public function qr()
    {
        $c = Configuration::first();
        if (!$this->listo() || !$c->qr_api_instance) {
            return response()->json(['success' => false, 'message' => 'Primero pulsa «Conectar».'], 422);
        }
        try {
            return ['success' => true, 'qr' => EvolutionClient::extractQr((new EvolutionClient())->qr($c->qr_api_instance))];
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'No se pudo obtener el QR.'], 502);
        }
    }

    public function desconectar()
    {
        $c = Configuration::first();
        if ($this->listo() && $c->qr_api_instance) {
            try {
                (new EvolutionClient())->deleteInstance($c->qr_api_instance);
            } catch (\Throwable $e) {
                Log::warning('[WhatsAppQR] desconectar: ' . $e->getMessage());
            }
        }
        $c->qr_api_instance = null;
        $c->qr_api_connection_state = 'disconnected';
        $c->qr_api_connected_phone = null;
        $c->qr_api_profile_name = null;
        $c->qr_api_connected_at = null;
        $c->save();

        return ['success' => true, 'message' => 'WhatsApp desconectado.'];
    }

    /**
     * Envía un comprobante en PDF (base64) con su mensaje. Lo usa el botón
     * «Enviar» de nota de venta, boleta y factura (QrApiTemplate.vue).
     */
    public function enviarDocumento(Request $request)
    {
        $data = $request->validate([
            'file'     => 'required|string',
            'number'   => 'required|string|max:20',
            'message'  => 'nullable|string|max:1000',
            'filename' => 'required|string|max:150',
        ]);

        $c = Configuration::first();
        if (!$this->listo() || !$c->qr_api_instance || $c->qr_api_connection_state !== 'open') {
            return response()->json(['success' => false, 'message' => 'Conecta el WhatsApp de la tienda en Configuración → WhatsApp.'], 422);
        }

        $numero = preg_replace('/\D/', '', $data['number']);
        if (strlen($numero) === 9) {
            $numero = '51' . $numero;
        }

        try {
            $r = (new EvolutionClient())->sendMedia($c->qr_api_instance, $numero, [
                'mediatype' => 'document',
                'mimetype'  => 'application/pdf',
                'media'     => $data['file'],
                'fileName'  => $data['filename'],
                'caption'   => $data['message'] ?? '',
            ]);
        } catch (\Throwable $e) {
            Log::warning('[WhatsAppQR] documento: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'No se pudo enviar por WhatsApp.'], 502);
        }

        if (EvolutionClient::failed($r)) {
            return response()->json(['success' => false, 'message' => 'WhatsApp rechazó el envío. Revisa el número.'], 422);
        }

        return ['success' => true, 'message' => 'Comprobante enviado por WhatsApp.'];
    }

    private function listo(): bool
    {
        return EvolutionClient::available()
            && Schema::connection('tenant')->hasColumn('configurations', 'qr_api_instance');
    }

    private function nombreInstancia(): string
    {
        $uuid = optional(app(Environment::class)->tenant())->uuid ?: 'tienda';

        return 'ebaemy-' . strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $uuid));
    }
}
