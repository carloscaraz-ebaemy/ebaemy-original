<?php

namespace App\Http\Controllers;

use App\Services\Marketplace\MarketplaceAuthService;
use App\Services\Marketplace\MarketplaceUserMergeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class MarketplaceAuthController extends Controller
{
    public function __construct(
        private MarketplaceAuthService $auth,
        private MarketplaceUserMergeService $merge,
    ) {}

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

        // POST-redirect-GET: redirigimos a /code para que el browser
        // tenga una URL legitima en GET (sobrevive refresh sin tirar 405).
        // Pasamos email y next via flash session (no en URL: evita
        // enumerar emails desde history).
        return redirect()->route('marketplace.auth.code_form')
            ->with('mkt_login_email', $data['email'])
            ->with('mkt_login_next', $data['next'] ?? null);
    }

    /** GET /marketplace/auth/code — pantalla para ingresar el codigo. */
    public function showCodeForm(Request $request)
    {
        $email = session('mkt_login_email');
        $next  = session('mkt_login_next');
        // Si llega aqui sin haber pedido magic link → mandamos a login.
        if (!$email) {
            return redirect()->route('marketplace.login');
        }
        // Re-flashear para que sobreviva el primer refresh.
        $request->session()->reflash();
        return view('marketplace.auth.code', [
            'email' => $email,
            'next'  => $next,
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

        // Merge ANTES del regenerate: necesitamos el session_id anonimo.
        $anonSessionId = $request->session()->getId();
        Auth::guard('marketplace')->login($user, true);
        $this->merge->mergeFromSession($user, $anonSessionId);
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

        $anonSessionId = $request->session()->getId();
        Auth::guard('marketplace')->login($user, true);
        $this->merge->mergeFromSession($user, $anonSessionId);
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

    /** POST /marketplace/auth/login — email + password clasico. */
    public function loginPassword(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email:rfc|max:190',
            'password' => 'required|string|min:1|max:200',
            'remember' => 'sometimes|boolean',
            'next'     => 'nullable|string|max:300',
        ]);
        try {
            $user = $this->auth->loginWithPassword($data['email'], $data['password'], $request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
        $anonSessionId = $request->session()->getId();
        Auth::guard('marketplace')->login($user, !empty($data['remember']));
        $this->merge->mergeFromSession($user, $anonSessionId);
        $request->session()->regenerate();
        return redirect($data['next'] ?: route('marketplace.account'))
            ->with('mkt_login_ok', 'Bienvenido de vuelta, ' . $user->name . '.');
    }

    /** GET /marketplace/register — form de registro completo. */
    public function showRegister(Request $request)
    {
        if (Auth::guard('marketplace')->check()) {
            return redirect()->route('marketplace.account');
        }
        return view('marketplace.auth.register', [
            'next' => $request->input('next'),
        ]);
    }

    /** POST /marketplace/register — crear cuenta con password. */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:120',
            'email'     => 'required|email:rfc|max:190',
            'phone'     => 'nullable|string|max:20',
            'password'  => 'required|string|min:8|max:200|confirmed',
            'marketing' => 'sometimes|boolean',
            'next'      => 'nullable|string|max:300',
        ]);
        try {
            $user = $this->auth->register($data, $request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
        $anonSessionId = $request->session()->getId();
        Auth::guard('marketplace')->login($user, true);
        $this->merge->mergeFromSession($user, $anonSessionId);
        $request->session()->regenerate();
        return redirect($data['next'] ?: route('marketplace.account'))
            ->with('mkt_login_ok', 'Cuenta creada. Bienvenido a ebaemy, ' . $user->name . '.');
    }

    /** GET /marketplace/auth/google — redirige a Google OAuth. */
    public function googleRedirect(Request $request)
    {
        // Guardamos 'next' en session para usarlo al volver del callback.
        $request->session()->put('mkt_google_next', $request->input('next'));
        if (!config('services.google.client_id') || !config('services.google.client_secret')) {
            return redirect()->route('marketplace.login')
                ->withErrors(['email' => 'Google login no esta configurado en este server.']);
        }
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirectUrl(url('/marketplace/auth/google/callback'))
            ->redirect();
    }

    /** GET /marketplace/auth/google/callback — recibe el code de Google. */
    public function googleCallback(Request $request)
    {
        if ($request->filled('error')) {
            return redirect()->route('marketplace.login')
                ->withErrors(['email' => 'Cancelaste el acceso con Google.']);
        }
        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl(url('/marketplace/auth/google/callback'))
                ->user();
        } catch (\Throwable $e) {
            logger()->warning('Google OAuth failed', ['err' => $e->getMessage()]);
            return redirect()->route('marketplace.login')
                ->withErrors(['email' => 'No pudimos completar el login con Google. Intenta de nuevo.']);
        }

        try {
            $user = $this->auth->loginOrRegisterGoogle($googleUser, $request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('marketplace.login')->withErrors($e->errors());
        }

        $anonSessionId = $request->session()->getId();
        Auth::guard('marketplace')->login($user, true);
        $this->merge->mergeFromSession($user, $anonSessionId);
        $next = $request->session()->pull('mkt_google_next');
        $request->session()->regenerate();

        return redirect($next ?: route('marketplace.account'))
            ->with('mkt_login_ok', 'Bienvenido, ' . $user->name . '.');
    }

    /** GET /marketplace/account/orders — historial cross-tenant. */
    public function accountOrders(Request $request)
    {
        $user = Auth::guard('marketplace')->user();
        if (!$user) {
            return redirect()->route('marketplace.login');
        }
        $orders = \DB::connection('system')->table('marketplace_user_orders as o')
            ->leftJoin('hostnames as h', 'h.id', '=', 'o.hostname_id')
            ->where('o.user_id', $user->id)
            ->orderByDesc('o.confirmed_at')
            ->orderByDesc('o.id')
            ->limit(50)
            ->select('o.*', 'h.fqdn as tenant_fqdn')
            ->get();
        return view('marketplace.auth.orders', [
            'user'   => $user,
            'orders' => $orders,
        ]);
    }

    /** GET /marketplace/account — resumen de actividad del comprador. */
    public function account(Request $request)
    {
        $user = Auth::guard('marketplace')->user();
        if (!$user) {
            return redirect()->route('marketplace.login');
        }

        // Counts en una sola tanda — todas son queries indexadas baratas.
        $favCount    = \DB::connection('system')->table('marketplace_favorites')
                          ->where('user_id', $user->id)->count();
        $ordersCount = \DB::connection('system')->table('marketplace_user_orders')
                          ->where('user_id', $user->id)
                          ->whereNotNull('confirmed_at')
                          ->count();
        // Ultimas 4 vistas para mostrar como "Sigue donde lo dejaste".
        $recentViews = \DB::connection('system')->table('marketplace_user_views as v')
            ->join('marketplace_listings as l', 'l.id', '=', 'v.listing_id')
            ->where('v.user_id', $user->id)
            ->where('l.is_active', true)
            ->where('l.status', 'active')
            ->orderByDesc('v.viewed_at')
            ->limit(4)
            ->select('l.id', 'l.title', 'l.slug', 'l.image_url', 'l.price', 'l.mp_price', 'v.viewed_at')
            ->get();
        // Top 3 categorias de interes (si el job ya corrio).
        $interests = \DB::connection('system')->table('marketplace_user_interests as i')
            ->leftJoin('marketplace_categories as c', 'c.id', '=', 'i.category_id')
            ->where('i.user_id', $user->id)
            ->orderByDesc('i.score')
            ->limit(3)
            ->select('c.name', 'c.full_slug', 'i.score')
            ->get();

        return view('marketplace.auth.account', [
            'user'         => $user,
            'favCount'     => $favCount,
            'ordersCount'  => $ordersCount,
            'recentViews'  => $recentViews,
            'interests'    => $interests,
        ]);
    }
}
