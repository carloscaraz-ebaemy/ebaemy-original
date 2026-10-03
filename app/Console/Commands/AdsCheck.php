<?php

namespace App\Console\Commands;

use App\Models\System\MarketplaceListing;
use App\Services\Marketplace\AdsConversionApi;
use App\Services\Marketplace\AdsTracking;
use Illuminate\Console\Command;

/**
 * Comprueba que la medición de publicidad del marketplace está de verdad
 * conectada — no que los campos estén rellenos.
 *
 * La diferencia importa: un token caducado, un ID mal pegado o un píxel de
 * otra cuenta pasan cualquier revisión visual y no miden nada. El único modo
 * de saberlo es mandar un evento y leer la respuesta de la plataforma.
 *
 *   php artisan ads:check            → sólo diagnostica, no manda nada
 *   php artisan ads:check --send     → manda un evento de prueba de verdad
 */
class AdsCheck extends Command
{
    protected $signature = 'ads:check {--send : Manda un evento de prueba a las plataformas configuradas}';

    protected $description = 'Verifica la medición de publicidad del marketplace (píxeles, tokens y entrega real de eventos)';

    public function handle(AdsConversionApi $api): int
    {
        $cfg = AdsTracking::config();

        $this->line('');
        $this->info('Medición de publicidad del marketplace');
        $this->line('');

        $this->table(['Pieza', 'Estado'], [
            ['Interruptor maestro', $cfg['enabled'] ? 'activo' : 'APAGADO — no se emite nada'],
            ['Píxel de Meta',       $cfg['meta_pixel']   ?: 'sin configurar'],
            ['Token CAPI de Meta',  $cfg['meta_token']   ? 'presente' : 'ausente — se perderán ventas'],
            ['Píxel de TikTok',     $cfg['tiktok_pixel'] ?: 'sin configurar'],
            ['Token de TikTok',     $cfg['tiktok_token'] ? 'presente' : 'ausente — se perderán ventas'],
            ['GA4',                 $cfg['ga4']          ?: 'sin configurar'],
            ['Código de prueba',    $cfg['test_code'] ? $cfg['test_code'] . ' — los eventos NO cuentan como conversión' : 'vacío (correcto en producción)'],
        ]);

        if (!$cfg['enabled']) {
            $this->warn('El interruptor maestro está apagado: ni los píxeles ni el servidor emiten nada.');
            $this->line('Se activa en /admin/marketplace/seo.');
        }

        if (!AdsTracking::browserEnabled()) {
            $this->warn('No hay ningún píxel que cargar en el navegador.');
        }

        if (!AdsTracking::serverEnabled()) {
            $this->warn('Sin API de conversiones: el Purchase sólo viajará desde el navegador,');
            $this->warn('y ahí se pierde una parte (iOS/ATT, bloqueadores, pestañas cerradas).');
        }

        // El feed y el píxel tienen que hablar del mismo producto. Si el
        // content_id no es el <g:id> del feed, la coincidencia de catálogo es
        // 0 % y los anuncios dinámicos no arrancan nunca.
        $sample = MarketplaceListing::published()->first();

        if (!$sample) {
            $this->warn('No hay ningún producto publicado: no se puede comprobar el content_id.');
        } else {
            $this->line('');
            $this->line('content_id de ejemplo: <info>' . AdsTracking::contentId($sample->id) . '</info>');
            $this->line('Tiene que ser idéntico al <g:id> del feed /feeds/meta-catalog.xml.');
        }

        if (!$this->option('send')) {
            $this->line('');
            $this->line('Para comprobar la entrega real: <info>php artisan ads:check --send</info>');

            return self::SUCCESS;
        }

        if (!AdsTracking::serverEnabled()) {
            $this->error('No se puede mandar nada: falta el píxel o el token de alguna plataforma.');

            return self::FAILURE;
        }

        if (!$cfg['test_code']) {
            $this->warn('Ojo: sin test_event_code, este evento de prueba entra como');
            $this->warn('conversión real en la cuenta publicitaria. Ponlo antes de probar.');

            if (!$this->confirm('¿Mandar de todas formas?', false)) {
                return self::SUCCESS;
            }
        }

        $this->line('');
        $this->line('Mandando un ViewContent de prueba…');

        $payload = AdsTracking::payload(
            'view_content',
            [[
                'content_id'   => $sample ? AdsTracking::contentId($sample->id) : 'mp_0',
                'content_name' => $sample->title ?? 'Prueba',
                'quantity'     => 1,
                'price'        => $sample ? round((float) $sample->display_price, 2) : 1.0,
            ]],
            $sample ? (float) $sample->display_price : 1.0,
            'ads-check-' . now()->timestamp
        );
        $payload['url'] = url('/marketplace');

        $results = $api->send('view_content', $payload, [
            'ip'         => '127.0.0.1',
            'user_agent' => 'ebaemy/ads:check',
        ]);

        $this->line('');

        $allOk = true;

        foreach ($results as $platform => $result) {
            if (!is_array($result)) {
                $this->warn(" {$platform}: {$result}");
                continue;
            }

            if ($result['ok'] ?? false) {
                $this->info(" {$platform}: entregado (" . json_encode($result) . ')');
            } else {
                $allOk = false;
                $this->error(" {$platform}: NO entregado — " . ($result['reason'] ?? 'motivo desconocido'));
            }
        }

        $this->line('');

        if (!$allOk) {
            // Un 200 con events_received = 0 es un fallo silencioso: la
            // plataforma aceptó la petición y descartó el evento.
            $this->error('Alguna plataforma no recibió el evento. Un código HTTP 200 no basta:');
            $this->error('lo que cuenta es events_received en Meta y code = 0 en TikTok.');

            return self::FAILURE;
        }

        $this->info('Las plataformas confirmaron la recepción del evento.');

        if ($cfg['test_code']) {
            $this->line('Míralo en Meta → Administrador de eventos → Eventos de prueba.');
        }

        return self::SUCCESS;
    }
}
