<?php

namespace App\Http\Controllers;

use App\Services\Marketplace\MarketplaceAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MarketplaceAuthController extends Controller
{
    public function __construct(private MarketplaceAuthService $auth) {}

    /** GET /marketplace/login — form de email. */
    public function showLogin(Request $request)
    {
        if (Auth::guard('marketplace')->check()) {
            return redirect($request->input('next', route('marketplace.account')));
        }
        return view('marketplace.auth.login', [
            'next' => $request->input('next'),
        ]);
    }

    /** POST /marketplace/auth/request — envia magic link. */
    public function requestLink(Request $request)
    {
        $data = $request->validate([
            'email'       => 'required|email:rfc|max:190',
            'marketing'   => 'sometimes|boolean',
            'next'        => 'nullable|string|max:300',
        ]);

        try {
            $this->auth->requestMagicLink(
                $data['email'],
                $request,
                (bool) ($data['marketing'] ?? false),
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()]);
        }

        // Por seguridad: SIEMPRE respondemos "te enviamos un correo",
        // existe o no el email. Evita enumeracion de cuentas.
        return view('marketplace.auth.code', [
            'email' => $data['email'],
            'next'  => $data['next'] ?? null,
        ]);
    }

    /** POST /marketplace/auth/verify-code — entrada manual del codigo. */
    public function verifyCode(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email:rfc|max:190',
            'code'  => 'required|digits:6',
            'next'  => 'nullable|string|max:300',
        ]);

        try {
            $user = $this->auth->verify($data['email'], null, $data['code'], $request);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['code' => $e->getMessage()]);
        }

        Auth::guard('marketplace')->login($user, true);
        $request->session()->regenerate();

        return redirect($data['next'] ?: route('marketplace.account'))
            ->with('mkt_login_ok', 'Bienvenido a ebaemy.');
    }

    /** GET /marketplace/auth/verify?token=... — consume magic link directo. */
    public function verifyToken(Request $request)
    {
        $token = (string) $request->query('token');
        if ($token === '') {
            return redirect()->route('marketplace.login')->withErrors(['email' => 'Link invalido.']);
        }

        try {
            $user = $this->auth->verify(null, $token, null, $request);
        } catch (\RuntimeException $e) {
            return redirect()->route('marketplace.login')->withErrors(['email' => $e->getMessage()]);
        }

        Auth::guard('marketplace')->login($user, true);
        $request->session()->regenerate();

        return redirect()->route('marketplace.account')
            ->with('mkt_login_ok', 'Bienvenido a ebaemy.');
    }

    /** POST /marketplace/auth/logout — cierra sesion global. */
    public function logout(Request $request)
    {
        Auth::guard('marketplace')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('marketplace.index')
            ->with('mkt_logout_ok', 'Sesion cerrada.');
    }

    /** GET /marketplace/account — placeholder de Mi cuenta. */
    public function account(Request $request)
    {
        $user = Auth::guard('marketplace')->user();
        if (!$user) {
            return redirect()->route('marketplace.login');
        }
        return view('marketplace.auth.account', [
            'user' => $user,
        ]);
    }
}
