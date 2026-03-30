<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\User;
use App\Services\System\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class TwoFactorController extends Controller
{
    // ── Verificación de código durante el login ───────────────────────────────

    public function showVerify(Request $request)
    {
        if (!$request->session()->has('2fa_pending_user_id')) {
            return redirect()->route('login');
        }
        return view('system.auth.two-factor-verify');
    }

    public function verify(Request $request)
    {
        $userId = $request->session()->get('2fa_pending_user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        // Rate-limit: máx 5 intentos por IP en 1 minuto
        $key = '2fa_verify_' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => 'Demasiados intentos. Espera un momento.']);
        }

        $request->validate(['code' => 'required|string']);

        $user = User::find($userId);
        if (!$user) {
            $request->session()->forget('2fa_pending_user_id');
            return redirect()->route('login');
        }

        if (!TotpService::verify($user->two_factor_secret, $request->code)) {
            RateLimiter::hit($key, 60);
            return back()->withErrors(['code' => 'Código incorrecto o expirado.']);
        }

        RateLimiter::clear($key);
        $request->session()->forget(['2fa_pending_user_id', '2fa_remember']);
        $request->session()->regenerate();

        Auth::guard('admin')->login($user, $request->session()->get('2fa_remember', false));

        return redirect()->intended('/dashboard');
    }

    // ── Setup: mostrar QR para configurar la app ──────────────────────────────

    public function showSetup()
    {
        $user = Auth::guard('admin')->user();

        // Generar secreto temporal (no confirmado aún)
        if (empty($user->attributes['two_factor_secret'])) {
            $user->two_factor_secret       = TotpService::generateSecret();
            $user->two_factor_confirmed_at = null;
            $user->save();
        }

        $secret   = $user->two_factor_secret;
        $qrUrl    = TotpService::getQrImageUrl($secret, $user->email);
        $otpauth  = TotpService::getOtpauthUrl($secret, $user->email);

        return view('system.auth.two-factor-setup', compact('secret', 'qrUrl', 'otpauth'));
    }

    public function enable(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $user = Auth::guard('admin')->user();

        if (!TotpService::verify($user->two_factor_secret, $request->code)) {
            return back()->withErrors(['code' => 'Código incorrecto. Escanea el QR de nuevo e intenta.']);
        }

        $user->two_factor_confirmed_at = now();
        $user->save();

        return redirect('/dashboard')->with('success', '✔ Autenticación de dos factores activada.');
    }

    // ── Desactivar 2FA ────────────────────────────────────────────────────────

    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
            'code'     => 'required|string',
        ]);

        $user = Auth::guard('admin')->user();

        if (!\Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'Contraseña incorrecta.']);
        }

        if (!TotpService::verify($user->two_factor_secret, $request->code)) {
            return back()->withErrors(['code' => 'Código 2FA incorrecto.']);
        }

        $user->two_factor_secret       = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return redirect('/dashboard')->with('success', '2FA desactivado correctamente.');
    }
}
