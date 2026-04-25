<?php

namespace App\Services\System;

use App\Models\System\Configuration as SystemConfiguration;
use App\Models\System\MarketingCampaign;
use App\Models\System\MarketingCampaignTarget;
use App\Models\System\MarketingContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Procesa el envío de campañas de marketing centralizadas. Bloquea cualquier
 * envío a contactos que NO tengan consent_marketing=true o que hayan optado
 * por salir (opted_out=true). Cada mensaje incluye el link de opt-out.
 *
 * Diseño consciente:
 *   - sin canal de envío configurado → log + skip
 *   - sin consent o opted_out → status=skipped con skip_reason
 *   - rate limit por canal (TODO Fase 3)
 */
class MarketingCampaignService
{
    /**
     * Materializa los targets de la campaña según su segmento. No envía.
     *
     * @return int  cantidad de targets creados
     */
    public function buildTargets(MarketingCampaign $campaign): int
    {
        $segment = $campaign->segment ?? [];
        $query = MarketingContact::query()->reachable();

        if (!empty($segment['hostname_id'])) {
            $query->where('hostname_id', $segment['hostname_id']);
        }
        if (!empty($segment['source'])) {
            $query->where('source', $segment['source']);
        }
        if (!empty($segment['tags']) && is_array($segment['tags'])) {
            foreach ($segment['tags'] as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        // Filtrar por canal: el contacto debe tener el dato necesario
        if ($campaign->channel === MarketingCampaign::CHANNEL_EMAIL) {
            $query->whereNotNull('email')->where('email', '!=', '');
        } else {
            $query->whereNotNull('phone')->where('phone', '!=', '');
        }

        $created = 0;
        $query->chunkById(500, function ($contacts) use ($campaign, &$created) {
            foreach ($contacts as $contact) {
                MarketingCampaignTarget::query()->updateOrCreate(
                    ['campaign_id' => $campaign->id, 'contact_id' => $contact->id],
                    ['status' => 'pending']
                );
                $created++;
            }
        });

        $campaign->update(['target_count' => $created]);

        return $created;
    }

    /**
     * Procesa los targets pendientes de la campaña hasta `limit`.
     * Devuelve {sent, failed, skipped}.
     */
    public function process(MarketingCampaign $campaign, int $limit = 100): array
    {
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        if (!in_array($campaign->status, [MarketingCampaign::STATUS_SCHEDULED, MarketingCampaign::STATUS_SENDING])) {
            $campaign->update(['status' => MarketingCampaign::STATUS_SENDING, 'started_at' => $campaign->started_at ?? now()]);
        }

        $targets = MarketingCampaignTarget::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->with('contact')
            ->limit($limit)
            ->get();

        foreach ($targets as $target) {
            $contact = $target->contact;
            if (!$contact) {
                $target->update(['status' => 'skipped', 'skip_reason' => 'invalid']);
                $skipped++;
                continue;
            }

            if (!$contact->canReceiveMarketing($campaign->channel)) {
                $reason = !$contact->consent_marketing ? 'no_consent'
                    : ($contact->opted_out ? 'opted_out' : 'missing_channel');
                $target->update(['status' => 'skipped', 'skip_reason' => $reason]);
                $skipped++;
                continue;
            }

            try {
                $this->sendOne($campaign, $contact);
                $target->update(['status' => 'sent', 'sent_at' => now()]);
                $contact->increment('sent_count');
                $contact->update(['last_sent_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                $target->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
                Log::warning('marketing send failed', [
                    'campaign' => $campaign->id, 'contact' => $contact->id, 'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        DB::table('marketing_campaigns')
            ->where('id', $campaign->id)
            ->update([
                'sent_count'   => DB::raw('sent_count + ' . $sent),
                'failed_count' => DB::raw('failed_count + ' . $failed),
                'updated_at'   => now(),
            ]);

        // Si ya no quedan pending, marcamos como sent
        $remaining = MarketingCampaignTarget::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->count();

        if ($remaining === 0) {
            $campaign->update(['status' => MarketingCampaign::STATUS_SENT, 'finished_at' => now()]);
        }

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => $skipped];
    }

    /**
     * Envío real para un único contacto. Renderiza el mensaje sustituyendo
     * variables {nombre} y {opt_out_url}, y delega al canal apropiado.
     */
    private function sendOne(MarketingCampaign $campaign, MarketingContact $contact): void
    {
        $optOutUrl = url('/unsubscribe/' . $contact->opt_out_token);

        $vars = [
            '{nombre}'       => $contact->name ?: 'amigo',
            '{opt_out_url}'  => $optOutUrl,
            '{ruc}'          => '',
        ];
        $body = strtr($campaign->message, $vars);

        // Asegurar que el opt-out URL esté presente — si el admin no lo
        // incluyó en el template, lo añadimos al final (no negociable).
        if (!str_contains($body, $optOutUrl) && !str_contains($body, 'unsubscribe')) {
            $body .= "\n\n— Para no recibir más promociones: " . $optOutUrl;
        }

        switch ($campaign->channel) {
            case MarketingCampaign::CHANNEL_EMAIL:
                $this->sendEmail($contact->email, $campaign->subject ?: $campaign->name, $body, $optOutUrl);
                break;
            case MarketingCampaign::CHANNEL_WHATSAPP:
                $this->sendWhatsApp($contact->phone, $body);
                break;
            case MarketingCampaign::CHANNEL_SMS:
                throw new \RuntimeException('SMS no implementado todavía (Fase 3)');
            default:
                throw new \RuntimeException('Canal desconocido: ' . $campaign->channel);
        }
    }

    private function sendEmail(string $to, string $subject, string $body, string $optOutUrl): void
    {
        SystemConfiguration::setConfigSmtpMail();

        $html = '<!DOCTYPE html><html><body style="font-family:sans-serif;color:#111;max-width:560px;margin:0 auto;padding:24px">';
        $html .= nl2br(htmlspecialchars($body));
        $html .= '<hr style="margin-top:24px;border:none;border-top:1px solid #e5e7eb">';
        $html .= '<p style="font-size:12px;color:#9ca3af;text-align:center">Recibes esto porque aceptaste novedades de ebaemy. ';
        $html .= '<a href="' . htmlspecialchars($optOutUrl) . '" style="color:#9ca3af">Cancelar suscripción</a></p>';
        $html .= '</body></html>';

        $safeSubject = mb_substr(trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $subject))), 0, 100);

        Mail::send([], [], function ($message) use ($to, $safeSubject, $html) {
            $message->to($to)->subject($safeSubject)->html($html);
        });
    }

    /**
     * Envío WhatsApp usando el driver del SISTEMA central (no de un tenant).
     * Reusa el WhatsAppService del tenant pero forzando el driver QR API o
     * Meta Cloud configurado en system.configurations.qr_api_*.
     */
    private function sendWhatsApp(string $phone, string $body): void
    {
        $config = SystemConfiguration::firstCached();
        if (!$config || empty($config->qr_api_url) || empty($config->qr_api_token)) {
            throw new \RuntimeException('WhatsApp no configurado en system.configurations (qr_api_url/token)');
        }

        // Cliente HTTP directo al gateway QR API del sistema central.
        // Evita acoplarnos al WhatsAppService del tenant — esa fachada
        // resuelve el driver según hostname activo, lo cual aquí no aplica.
        $url = rtrim($config->qr_api_url, '/');
        $payload = [
            'token'   => $config->qr_api_token,
            'to'      => $this->normalizePhone($phone),
            'message' => $body,
        ];

        $resp = \Illuminate\Support\Facades\Http::timeout(10)->post($url, $payload);

        if (!$resp->successful()) {
            throw new \RuntimeException('Gateway WhatsApp respondió ' . $resp->status() . ': ' . mb_substr($resp->body(), 0, 200));
        }
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        // Perú: si vienen 9 dígitos asumimos número móvil → prefijar 51
        if (strlen($digits) === 9 && $digits[0] === '9') {
            return '51' . $digits;
        }
        return $digits;
    }
}
