<?php

namespace App\Http\Controllers;

use App\Models\System\MarketingContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook inbound para procesar respuestas de marketing (WhatsApp/SMS) y
 * activar opt-out automático cuando el contacto escribe palabras clave.
 *
 * Compatible con dos formatos:
 *
 *   1) Meta Cloud API webhook (POST con verify GET handshake):
 *      query.hub.mode=subscribe + hub.challenge → devolver challenge
 *      body.entry[].changes[].value.messages[].text.body → procesar
 *
 *   2) QR API gateway genérico:
 *      POST {phone, message, ...} simple → procesar directo
 *
 * Activación: configurar el endpoint en Meta Business Manager o en el panel
 * del gateway QR API:
 *   https://ebaemy.com/webhooks/marketing/inbound
 *
 * Seguridad: el endpoint valida WEBHOOK_VERIFY_TOKEN env. Sin token configurado,
 * acepta requests (modo dev). En prod siempre setear el token.
 */
class MarketingInboundController extends Controller
{
    /**
     * Palabras que disparan opt-out automático. Comparación case-insensitive
     * sobre el texto trimmed completo del mensaje.
     */
    private const OPT_OUT_KEYWORDS = [
        'stop', 'baja', 'cancelar', 'unsubscribe', 'salir',
        'no enviar', 'no más', 'parar', 'detener',
    ];

    /**
     * Handshake de verificación de Meta Cloud API.
     * https://developers.facebook.com/docs/graph-api/webhooks/getting-started
     */
    public function verify(Request $request)
    {
        $expected = env('WHATSAPP_WEBHOOK_VERIFY_TOKEN');
        $mode     = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token    = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        if ($mode === 'subscribe' && (!$expected || $token === $expected)) {
            return response($challenge, 200);
        }

        return response('forbidden', 403);
    }

    /**
     * Procesa un mensaje inbound. Si el texto contiene una keyword de
     * opt-out, marca el contact correspondiente como opted_out.
     */
    public function inbound(Request $request)
    {
        try {
            $messages = $this->extractMessages($request);

            foreach ($messages as $msg) {
                $this->processMessage($msg['phone'], $msg['text']);
            }

            return response()->json(['ok' => true, 'processed' => count($messages)]);
        } catch (\Throwable $e) {
            Log::error('MarketingInbound webhook failed', [
                'error'   => $e->getMessage(),
                'payload' => $request->all(),
            ]);
            // Devolver 200 para que el gateway no reintente indefinidamente
            return response()->json(['ok' => false, 'error' => 'logged'], 200);
        }
    }

    /**
     * Normaliza el payload de cualquier proveedor a una lista de
     * {phone, text} para procesar.
     */
    private function extractMessages(Request $request): array
    {
        $out = [];

        // Meta Cloud API: entry[].changes[].value.messages[]
        $entries = $request->input('entry', []);
        if (is_array($entries)) {
            foreach ($entries as $entry) {
                foreach (($entry['changes'] ?? []) as $change) {
                    $messages = $change['value']['messages'] ?? [];
                    foreach ($messages as $m) {
                        $phone = $m['from'] ?? null;
                        $text  = $m['text']['body'] ?? null;
                        if ($phone && $text) {
                            $out[] = ['phone' => $phone, 'text' => $text];
                        }
                    }
                }
            }
        }

        // QR API genérico: payload plano {phone/from, message/text/body}
        if (empty($out)) {
            $phone = $request->input('phone') ?? $request->input('from') ?? $request->input('sender');
            $text  = $request->input('message') ?? $request->input('text') ?? $request->input('body');
            if ($phone && $text) {
                $out[] = ['phone' => $phone, 'text' => $text];
            }
        }

        return $out;
    }

    private function processMessage(string $phone, string $text): void
    {
        $normalized = trim(mb_strtolower($text));

        $isOptOut = false;
        foreach (self::OPT_OUT_KEYWORDS as $kw) {
            if ($normalized === $kw || str_starts_with($normalized, $kw . ' ') || str_starts_with($normalized, $kw . '.')) {
                $isOptOut = true;
                break;
            }
        }

        if (!$isOptOut) {
            return;
        }

        $cleanPhone = $this->normalizePhone($phone);

        $contacts = MarketingContact::query()
            ->where(function ($q) use ($phone, $cleanPhone) {
                $q->where('phone', $phone)
                  ->orWhere('phone', $cleanPhone)
                  ->orWhere('phone', 'like', '%' . substr($cleanPhone, -9));
            })
            ->where('opted_out', false)
            ->get();

        foreach ($contacts as $contact) {
            $contact->update([
                'opted_out'      => true,
                'opted_out_at'   => now(),
                'opt_out_reason' => 'auto: STOP keyword in WhatsApp/SMS reply',
            ]);

            Log::info('MarketingInbound auto opt-out', [
                'contact_id' => $contact->id,
                'phone'      => $phone,
                'keyword'    => $normalized,
            ]);
        }
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? $phone;
    }
}
