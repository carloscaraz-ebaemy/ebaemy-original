<?php

namespace App\Services\Security\Notify;

use App\Services\Security\Alert;
use App\Services\Security\Severity;
use App\Services\Security\Support\AgentConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;

/**
 * Envia las alertas por Telegram, correo y webhook (Slack).
 *
 * Solo se notifica a partir de la severidad minima configurada (ALTA por
 * defecto): el objetivo es que un mensaje del agente siempre merezca abrirse.
 *
 * Las credenciales salen SIEMPRE de variables de entorno. Un canal que falle
 * no impide que los demas salgan.
 */
final class NotificationDispatcher
{
    public function __construct(private readonly AgentConfig $config) {}

    /**
     * @param  Alert[]  $alerts
     * @return array{sent:array<string,int>,errors:array<int,string>,filtered:int}
     */
    public function dispatch(array $alerts): array
    {
        $minimum = (string) $this->config->get('notify.min_severity', Severity::ALTA);

        $notifiable = array_values(array_filter(
            $alerts,
            fn (Alert $alert) => Severity::atLeast($alert->severity, $minimum)
        ));

        $result = ['sent' => [], 'errors' => [], 'filtered' => count($notifiable)];

        if (!$notifiable) {
            return $result;
        }

        foreach (['telegram', 'mail', 'webhook'] as $channel) {
            if (!$this->config->get("notify.{$channel}.enabled", false)) continue;

            try {
                $this->{$channel}($notifiable);
                $result['sent'][$channel] = count($notifiable);
            } catch (\Throwable $e) {
                $result['errors'][] = "{$channel}: {$e->getMessage()}";
                Log::warning("[SecurityAgent] Fallo el canal de notificacion {$channel}.", ['error' => $e->getMessage()]);
            }
        }

        return $result;
    }

    // ── Canales ───────────────────────────────────────────────────────────────

    private function telegram(array $alerts): void
    {
        $token  = $this->config->get('notify.telegram.token');
        $chatId = $this->config->get('notify.telegram.chat_id');

        if (!$token || !$chatId) {
            throw new \RuntimeException('Faltan SECURITY_AGENT_TELEGRAM_TOKEN o SECURITY_AGENT_TELEGRAM_CHAT_ID en .env');
        }

        // Telegram corta en 4096 caracteres: mandamos en tandas de 8 alertas.
        foreach (array_chunk($alerts, 8) as $chunk) {
            $lines = ['*Agente de Seguridad* — ' . count($chunk) . ' alerta(s)'];

            foreach ($chunk as $alert) {
                $lines[] = '';
                $lines[] = Severity::emoji($alert->severity) . ' *' . $this->md($alert->severity) . '* — ' . $this->md($alert->tenant ?? 'sistema');
                $lines[] = $this->md($alert->title);
                $lines[] = '_' . $this->md(mb_substr($alert->recommendation, 0, 400)) . '_';
            }

            $response = Http::timeout(15)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id'    => $chatId,
                'text'       => mb_substr(implode("\n", $lines), 0, 4000),
                'parse_mode' => 'Markdown',
            ]);

            if ($response->failed()) {
                throw new \RuntimeException('Telegram respondio ' . $response->status() . ': ' . $response->body());
            }
        }
    }

    private function mail(array $alerts): void
    {
        $to = (array) $this->config->get('notify.mail.to', []);

        if (!$to) {
            throw new \RuntimeException('Falta SECURITY_AGENT_MAIL_TO en .env');
        }

        $body = '';
        foreach ($alerts as $alert) {
            $body .= $alert->summary() . "\n";
            $body .= "  Que hacer: {$alert->recommendation}\n";
            $body .= '  Evidencia: ' . json_encode($alert->evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        }

        $subject = sprintf('[Seguridad] %d alerta(s) — mayor severidad: %s', count($alerts), $alerts[0]->severity);

        Mail::raw($body, function ($message) use ($to, $subject) {
            $message->to($to)->subject($subject);
        });
    }

    private function webhook(array $alerts): void
    {
        $url = $this->config->get('notify.webhook.url');

        if (!$url) {
            throw new \RuntimeException('Falta SECURITY_AGENT_WEBHOOK_URL en .env');
        }

        $text = "*Agente de Seguridad* — " . count($alerts) . " alerta(s)\n";
        foreach ($alerts as $alert) {
            $text .= "\n" . $alert->summary() . "\n> " . mb_substr($alert->recommendation, 0, 300) . "\n";
        }

        $response = Http::timeout(15)->post($url, [
            'text'   => $text,
            'alerts' => array_map(fn (Alert $a) => $a->jsonSerialize(), $alerts),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('El webhook respondio ' . $response->status());
        }
    }

    /** Escapa lo que Telegram interpreta como formato. */
    private function md(string $value): string
    {
        return str_replace(['_', '*', '[', ']', '`'], ['\_', '\*', '\[', '\]', '\`'], $value);
    }
}
