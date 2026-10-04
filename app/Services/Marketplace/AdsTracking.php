<?php

namespace App\Services\Marketplace;

use App\Models\System\Configuration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/**
 * Medición de publicidad del marketplace — la fuente única de verdad sobre
 * QUÉ se mide y con QUÉ identificadores.
 *
 * Dos reglas que no se negocian, porque romperlas se nota semanas después y
 * para entonces ya se gastó el presupuesto:
 *
 *  1. El `content_id` de todo evento es `mp_{listing_id}` — exactamente el
 *     mismo `<g:id>` que emite el feed en MarketplaceController@metaCatalog.
 *     Si el píxel manda el `item_id` del tenant, Meta y TikTok reportan 0 %
 *     de coincidencia de catálogo y los anuncios dinámicos no arrancan nunca.
 *  2. Un mismo evento se manda dos veces (navegador y servidor) con el MISMO
 *     `event_id`, para que la plataforma deduplique. Sin ese id se cuenta
 *     doble y el ROAS sale inflado.
 *
 * Esta clase no envía nada. Prepara. El navegador lo emite desde el partial
 * `marketplace/partials/tracking.blade.php`; el servidor, desde
 * `AdsConversionApi` a través del job `SendAdsConversion`.
 */
class AdsTracking
{
    /** Clave de sesión donde vive la atribución capturada en la primera visita. */
    public const ATTRIBUTION_KEY = 'mp_attribution';

    /** Clave de sesión del consentimiento de cookies de medición. */
    public const CONSENT_KEY = 'mp_ads_consent';

    // ══════════════════════════════════════════════════════════════
    // Configuración
    // ══════════════════════════════════════════════════════════════

    /**
     * Configuración de medición, ya resuelta. Nunca devuelve un id vacío como
     * cadena vacía: o hay valor, o es null, para que la vista pueda decidir
     * con un simple `if` y no emita un <script> hueco.
     */
    public static function config(): array
    {
        $c = Configuration::firstCached();

        $clean = fn ($v) => ($v = trim((string) $v)) !== '' ? $v : null;

        return [
            'enabled'      => (bool) ($c->marketplace_ads_enabled ?? false),
            'meta_pixel'   => $clean($c->marketplace_meta_pixel_id ?? null),
            'meta_token'   => $clean($c->marketplace_meta_capi_token ?? null),
            'tiktok_pixel' => $clean($c->marketplace_tiktok_pixel_id ?? null),
            'tiktok_token' => $clean($c->marketplace_tiktok_capi_token ?? null),
            'ga4'          => $clean($c->marketplace_ga4_id ?? null),
            'test_code'    => $clean($c->marketplace_ads_test_code ?? null),
        ];
    }

    /**
     * ¿Hay algo que emitir en el navegador? Con el interruptor maestro apagado
     * la respuesta es no, aunque los IDs estén puestos.
     */
    public static function browserEnabled(): bool
    {
        $cfg = static::config();

        return $cfg['enabled']
            && ($cfg['meta_pixel'] || $cfg['tiktok_pixel'] || $cfg['ga4']);
    }

    /** ¿Se puede mandar el evento desde el servidor a alguna plataforma? */
    public static function serverEnabled(): bool
    {
        $cfg = static::config();

        return $cfg['enabled']
            && (($cfg['meta_pixel'] && $cfg['meta_token'])
             || ($cfg['tiktok_pixel'] && $cfg['tiktok_token']));
    }

    /**
     * El comprador aceptó la medición. Hasta que lo haga, el partial no carga
     * ningún píxel: el marketplace es público y de cara a consumidores
     * peruanos (Ley 29733).
     */
    public static function hasConsent(): bool
    {
        return Session::get(self::CONSENT_KEY) === 'granted';
    }

    /**
     * Lo que el comprador decidió: 'granted', 'denied' o null si todavía no
     * ha decidido. Se guarda junto al pedido para que el envío server-side
     * pueda respetarlo incluso cuando no hay sesión — el webhook de la
     * pasarela, por ejemplo.
     */
    public static function consentState(): ?string
    {
        $v = Session::get(self::CONSENT_KEY);

        return in_array($v, ['granted', 'denied'], true) ? $v : null;
    }

    /**
     * El consentimiento listo para guardar en `marketplace_orders` o
     * `marketplace_leads`. Vacío cuando no hay decisión, para no escribir
     * ruido.
     */
    public static function consentColumn(): array
    {
        $estado = static::consentState();

        return $estado ? ['ads_consent' => $estado] : [];
    }

    // ══════════════════════════════════════════════════════════════
    // Identificadores
    // ══════════════════════════════════════════════════════════════

    /**
     * El id de catálogo. Un solo sitio en todo el sistema que lo construye,
     * para que no pueda divergir del feed.
     *
     * @see \App\Http\Controllers\MarketplaceController::metaCatalog()
     */
    public static function contentId($listingId): string
    {
        return 'mp_' . (int) $listingId;
    }

    /**
     * Id de evento compartido entre navegador y servidor. Determinista cuando
     * se le pasa una semilla (el número de pedido), porque el Purchase del
     * navegador y el del servidor tienen que coincidir aunque los emita otra
     * petición y hasta otro día.
     */
    public static function eventId(string $event, ?string $seed = null): string
    {
        if ($seed !== null && $seed !== '') {
            return strtolower($event) . '.' . sha1($event . '|' . $seed);
        }

        return strtolower($event) . '.' . Str::random(24);
    }

    // ══════════════════════════════════════════════════════════════
    // Atribución
    // ══════════════════════════════════════════════════════════════

    /**
     * De dónde vino este visitante. Se captura una sola vez por sesión (el
     * primer aterrizaje) y sobrevive a toda la navegación hasta el checkout.
     */
    public static function attribution(): array
    {
        return (array) Session::get(self::ATTRIBUTION_KEY, []);
    }

    /**
     * Las columnas de atribución listas para escribir en `marketplace_orders`
     * o `marketplace_leads`. Devuelve sólo claves con valor, para no sobrescribir
     * con null lo que una visita anterior ya hubiera dejado puesto.
     */
    public static function attributionColumns(): array
    {
        $a = static::attribution();

        $cols = [
            'utm_source'      => $a['utm_source']   ?? null,
            'utm_medium'      => $a['utm_medium']   ?? null,
            'utm_campaign'    => $a['utm_campaign'] ?? null,
            'utm_content'     => $a['utm_content']  ?? null,
            'utm_term'        => $a['utm_term']     ?? null,
            'click_id_fb'     => $a['fbclid']       ?? null,
            'click_id_tt'     => $a['ttclid']       ?? null,
            'click_id_google' => $a['gclid']        ?? null,
        ];

        return array_filter($cols, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * El parámetro `fbc` que espera la Conversions API de Meta. No es el
     * `fbclid` pelado: Meta exige el formato `fb.1.{timestamp_ms}.{fbclid}` y
     * descarta el evento sin avisar si llega de otra forma.
     */
    public static function metaFbc(): ?string
    {
        $a = static::attribution();

        if (empty($a['fbclid'])) {
            return null;
        }

        $ms = (int) (($a['captured_at'] ?? time()) * 1000);

        return 'fb.1.' . $ms . '.' . $a['fbclid'];
    }

    // ══════════════════════════════════════════════════════════════
    // Payloads
    // ══════════════════════════════════════════════════════════════

    /**
     * Un item de evento a partir de una línea del carrito
     * (MarketplaceCartService::add() devuelve exactamente esta forma).
     */
    public static function itemFromCartLine(array $line): array
    {
        return [
            'content_id'   => static::contentId($line['listing_id'] ?? 0),
            'content_name' => (string) ($line['title'] ?? ''),
            'quantity'     => max(1, (int) ($line['quantity'] ?? 1)),
            'price'        => round((float) ($line['price'] ?? 0), 2),
        ];
    }

    /**
     * El payload que consume `window.mpTrack()` en el navegador.
     *
     * `value` se redondea y se fuerza a float: el input numérico de Element UI
     * devuelve `0` en vez de `null`, y un Purchase con value 0 envenena el
     * ROAS de la cuenta publicitaria sin posibilidad de corregirlo después.
     */
    public static function payload(string $event, array $items, float $value, ?string $seed = null): array
    {
        $payload = [
            'event'    => $event,
            'currency' => 'PEN',
            'value'    => round($value, 2),
            'items'    => array_values($items),
        ];

        // `event_id` sólo cuando hay semilla, es decir cuando este mismo evento
        // también se manda desde el servidor y hay que deduplicar. Ponerlo en
        // un evento que vive sólo en el navegador sería contraproducente: el
        // payload se renderiza una vez, así que un segundo AddToCart legítimo
        // llegaría con el id del primero y la plataforma lo descartaría.
        if ($seed !== null && $seed !== '') {
            $payload['event_id'] = static::eventId($event, $seed);
        }

        return $payload;
    }

    /**
     * Datos del navegador que la API de conversiones necesita para casar el
     * evento con la persona. Se recogen en la petición, no en el job: dentro
     * de la cola ya no hay request del comprador.
     */
    /**
     * Los mismos identificadores, pero sacados del PEDIDO en vez de la
     * petición. Es lo que permite medir la venta desde el webhook de la
     * pasarela, donde no hay comprador delante: la petición la hace
     * MercadoPago desde sus servidores, así que su IP y su user agent no
     * dicen nada de quien compró — mandarlos sería peor que no mandar nada.
     *
     * Los click ids salen de las columnas de atribución que se escribieron al
     * crear el pedido. El `fbc` necesita además un instante: se usa la fecha
     * del pedido, que es lo más cercano al clic que se conserva.
     */
    public static function userDataFromOrder($order): array
    {
        $datos = [];

        if (!empty($order->click_id_fb)) {
            $ms = (int) (($order->created_at ? $order->created_at->timestamp : time()) * 1000);
            $datos['fbc'] = 'fb.1.' . $ms . '.' . $order->click_id_fb;
        }

        if (!empty($order->click_id_tt)) {
            $datos['ttclid'] = $order->click_id_tt;
        }

        return $datos;
    }

    public static function userDataFromRequest(Request $request): array
    {
        return array_filter([
            'ip'         => $request->ip(),
            'user_agent' => substr((string) $request->header('User-Agent'), 0, 500),
            'fbc'        => static::metaFbc(),
            'fbp'        => $request->cookie('_fbp'),
            'ttclid'     => static::attribution()['ttclid'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
