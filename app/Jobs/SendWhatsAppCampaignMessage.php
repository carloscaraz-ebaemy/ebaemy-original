<?php

namespace App\Jobs;

use App\Models\Tenant\Person;
use App\Models\Tenant\WhatsAppOfferCampaign;
use App\Models\Tenant\WhatsAppOfferCampaignMessage;
use App\Services\Tenant\WhatsAppOfferCampaignService;
use App\Services\Tenant\WhatsAppService;
use Illuminate\Support\Facades\Log;

/**
 * UN mensaje de una campaña de WhatsApp por productos.
 *
 * La campaña no manda en bucle: cada mensaje es un job con su `delay`, de
 * modo que salen espaciados (ver WhatsAppOfferCampaignService::programarLote)
 * y el número de la tienda no parece un robot. Es WhatsApp-Web (Evolution):
 * Meta bloquea números que disparan cientos de mensajes seguidos.
 */
class SendWhatsAppCampaignMessage extends TenantAwareJob
{
    public int $tries = 1;

    public function __construct(public int $messageId)
    {
        parent::__construct();
    }

    public function handle(WhatsAppService $wa): void
    {
        $msg = WhatsAppOfferCampaignMessage::find($this->messageId);
        if (!$msg || $msg->status !== 'pending') {
            return; // ya enviado, cancelado o borrado
        }

        $campaign = WhatsAppOfferCampaign::find($msg->campaign_id);
        if (!$campaign || in_array($campaign->status, ['cancelled', 'paused'], true)) {
            return; // la tienda la detuvo: el mensaje se queda pendiente
        }

        $person = Person::find($msg->person_id);
        if (!$person || $person->whatsapp_opt_out) {
            $msg->update(['status' => 'skipped', 'error_message' => 'El cliente pidió no recibir avisos.']);
            $this->cerrarSiTermino($campaign);
            return;
        }

        $texto = data_get($msg->payload, 'text');
        $ok = false;
        $error = null;

        if (!$wa->isConfigured()) {
            $error = 'WhatsApp no está conectado en la tienda.';
        } elseif (!$texto) {
            $error = 'Mensaje vacío.';
        } else {
            try {
                $ok = $wa->send((string) ($msg->phone ?: $person->telephone), $texto);
                if (!$ok) {
                    $error = 'El proveedor de WhatsApp rechazó el envío (ver registro de WhatsApp).';
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                Log::warning('[WhatsAppCampaign] error enviando', ['message_id' => $msg->id, 'error' => $error]);
            }
        }

        $msg->update([
            'status'        => $ok ? 'sent' : 'failed',
            'error_message' => $ok ? null : mb_substr((string) $error, 0, 500),
            'sent_at'       => $ok ? now() : null,
        ]);

        $campaign->increment($ok ? 'sent_count' : 'failed_count');
        if ($campaign->status === 'queued') {
            $campaign->update(['status' => 'processing', 'started_at' => $campaign->started_at ?: now()]);
        }

        $this->cerrarSiTermino($campaign);
    }

    private function cerrarSiTermino(WhatsAppOfferCampaign $campaign): void
    {
        (new WhatsAppOfferCampaignService())->cerrarSiTermino($campaign->fresh());
    }

    public function failed(\Throwable $e): void
    {
        Log::error("SendWhatsAppCampaignMessage falló [{$this->messageId}]: " . $e->getMessage());
    }
}
