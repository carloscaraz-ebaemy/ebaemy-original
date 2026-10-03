<?php

namespace App\Http\Middleware;

use App\Services\Marketplace\AdsTracking;
use Closure;
use Illuminate\Http\Request;

/**
 * Guarda de dónde vino el visitante, una sola vez por sesión.
 *
 * Se captura en el PRIMER aterrizaje y no se vuelve a tocar: si alguien llega
 * por un anuncio de TikTok, navega, se va y vuelve escribiendo la URL a mano,
 * la venta sigue siendo de TikTok. Sobrescribir aquí sería regalarle la
 * conversión al tráfico directo.
 *
 * La excepción es un clic de anuncio nuevo: si llega un `fbclid`/`ttclid`/`gclid`
 * o un `utm_source`, es una campaña distinta y esa sí reemplaza a la anterior.
 *
 * No hace consultas ni escribe en base de datos — sólo sesión. Va sobre las
 * rutas públicas del marketplace, que son las que reciben el tráfico pagado.
 */
class CaptureMarketplaceAttribution
{
    /** Los parámetros que miramos. Nada más entra en la sesión. */
    private const UTM_KEYS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
    ];

    private const CLICK_KEYS = ['fbclid', 'ttclid', 'gclid'];

    public function handle(Request $request, Closure $next)
    {
        // Sólo GET: un POST no es un aterrizaje de anuncio.
        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        $incoming = [];

        foreach (self::UTM_KEYS as $key) {
            if ($v = $this->clean($request->query($key))) {
                $incoming[$key] = $v;
            }
        }
        foreach (self::CLICK_KEYS as $key) {
            if ($v = $this->clean($request->query($key), 255)) {
                $incoming[$key] = $v;
            }
        }

        // La sesión se toma del request, no de la fachada: este middleware
        // ya la tiene delante y así no depende del contenedor global.
        $existing = (array) $request->session()->get(AdsTracking::ATTRIBUTION_KEY, []);

        // Nada nuevo y ya hay origen guardado → no se toca.
        if (empty($incoming)) {
            if (empty($existing)) {
                // Primera visita sin parámetros: registramos el referrer para
                // poder distinguir "directo" de "vino de Google/Instagram
                // orgánico" en el informe. No es atribución de pago.
                $ref = $this->clean($request->headers->get('referer'), 255);
                if ($ref && !$this->isOwnDomain($ref, $request->getHost())) {
                    $request->session()->put(AdsTracking::ATTRIBUTION_KEY, [
                        'referrer'    => $ref,
                        'landing'     => $this->clean($request->fullUrl(), 500),
                        'captured_at' => time(),
                    ]);
                }
            }

            return $next($request);
        }

        $request->session()->put(AdsTracking::ATTRIBUTION_KEY, $incoming + [
            'landing'     => $this->clean($request->fullUrl(), 500),
            'captured_at' => time(),
        ]);

        return $next($request);
    }

    private function clean($value, int $max = 180): ?string
    {
        $value = trim(strip_tags((string) $value));

        return $value !== '' ? mb_substr($value, 0, $max) : null;
    }

    /** Un referrer de nuestro propio dominio no es una fuente de tráfico. */
    private function isOwnDomain(string $referrer, string $currentHost): bool
    {
        $host = parse_url($referrer, PHP_URL_HOST);

        return $host !== null && str_contains($currentHost, (string) $host);
    }
}
