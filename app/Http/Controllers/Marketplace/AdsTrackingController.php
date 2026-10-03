<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\AdsTracking;
use Illuminate\Http\Request;

/**
 * Consentimiento de cookies de medición del marketplace.
 *
 * Mientras no haya un 'granted' en la sesión, el partial de tracking no carga
 * ningún píxel. El marketplace es público y de cara a consumidores peruanos
 * (Ley 29733), así que la decisión del comprador se guarda en servidor y no
 * sólo en localStorage: el navegador puede limpiarlo, y un "rechazar" que se
 * olvida y vuelve a preguntar en la siguiente visita no es un rechazo.
 *
 * Endpoint público a propósito — no identifica a nadie, sólo guarda una
 * preferencia en la sesión del visitante. Con throttle para que no se pueda
 * usar como generador de sesiones.
 */
class AdsTrackingController extends Controller
{
    public function consent(Request $request)
    {
        $data = $request->validate([
            'consent' => 'required|in:granted,denied',
        ]);

        $request->session()->put(AdsTracking::CONSENT_KEY, $data['consent']);

        return response()->json([
            'success' => true,
            'consent' => $data['consent'],
        ]);
    }
}
