<?php

namespace App\Http\Controllers;

use Hyn\Tenancy\Models\Hostname;
use Illuminate\Http\Request;

/**
 * Landing pública para captar vendedores del marketplace ebaemy.
 *
 * Rutas:
 *   GET  /seller        → landing informativa (registro nuevo)
 *   GET  /seller/access → form para sellers existentes que quieren ir a
 *                         su panel: ingresan su subdominio y redirigimos
 *                         a https://{subdominio}.ebaemy.com/login
 *   POST /seller/access → procesa el form, valida que el tenant exista
 *                         y redirige
 */
class SellerLandingController extends Controller
{
    public function show()
    {
        return view('seller.landing');
    }

    public function access()
    {
        return view('seller.access');
    }

    public function accessGo(Request $request)
    {
        $sub = strtolower(trim((string) $request->input('subdomain', '')));

        if (!preg_match('/^[a-z0-9][a-z0-9\-]{1,62}$/', $sub)) {
            return back()
                ->withInput()
                ->withErrors(['subdomain' => 'Subdominio inválido. Solo letras minúsculas, números y guiones.']);
        }

        $exists = Hostname::query()
            ->where('fqdn', 'like', $sub . '.%')
            ->exists();

        if (!$exists) {
            return back()
                ->withInput()
                ->withErrors(['subdomain' => 'No encontramos una tienda con ese subdominio. Verifica que sea correcto.']);
        }

        return redirect()->away('https://' . $sub . '.ebaemy.com/login');
    }
}
