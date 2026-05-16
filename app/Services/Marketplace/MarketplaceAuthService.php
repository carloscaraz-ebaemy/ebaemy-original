<?php

namespace App\Services\Marketplace;

use App\Models\System\MarketplaceUser;
use App\Models\System\MarketplaceUserConsent;
use App\Models\System\MarketplaceUserMagicLink;
use App\Models\System\MarketplaceUserPreference;
use App\Mail\Marketplace\MarketplaceMagicLinkMail;
use App\Mail\Marketplace\MarketplaceWelcomeMail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de autenticacion del comprador.
 *
 * Responsabilidades:
 *  - Generar y enviar magic link + codigo 6 digitos.
 *  - Validar magic link / codigo.
 *  - Crear el MarketplaceUser si no existe (registro pasivo).
 *  - Rate limiting de solicitudes.
 *  - Auto-grant de consent transaccional al crear cuenta (necesario
 *    para enviar el propio magic link); marketing requiere opt-in
 *    explicito en el form de login.
 */
class MarketplaceAuthService
{
    public const TOKEN_TTL_MINUTES = 15;
    public const MAX_REQUESTS_PER_EMAIL_HOUR = 3;
    public const MAX_REQUESTS_PER_IP_HOUR    = 10;
    public const MAX_CODE_ATTEMPTS           = 5;

    /**
     * Solicita un magic link. Devuelve token cleartext si se envio,
     * o lanza \RuntimeException con mensaje user-facing si rate-limited
     * o email invalido.
     *
     * Por seguridad, NUNCA reveles si el email existe o no — siempre
     * responde "te enviamos un email" en el controller.
     */
    public function requestMagicLink(string $email, Request $request, bool $marketingOptIn = false): array
    {
        $email = strtolower(trim($email));

        // Rate limit por email (3/hora) y por IP (10/hora).
        $emailKey = 'mkt_magic:email:' . sha1($email);
        $ipKey    = 'mkt_magic:ip:'    . sha1((string) $request->ip());
        if (RateLimiter::tooManyAttempts($emailKey, self::MAX_REQUESTS_PER_EMAIL_HOUR)) {
            throw new \RuntimeException('Demasiadas solicitudes para este email. Intenta de nuevo en 1 hora.');
        }
        if (RateLimiter::tooManyAttempts($ipKey, self::MAX_REQUESTS_PER_IP_HOUR)) {
            throw new \RuntimeException('Demasiadas solicitudes desde tu conexion. Intenta de nuevo en 1 hora.');
        }
        RateLimiter::hit($emailKey, 3600);
        RateLimiter::hit($ipKey,    3600);

        $token = Str::random(40);
        $code  = (string) random_int(100000, 999999);

        MarketplaceUserMagicLink::create([
            'email'      => $email,
            'token_hash' => hash('sha256', $token),
            'code_hash'  => hash('sha256', $code),
            'expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
            'ip'         => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        Mail::to($email)->send(new MarketplaceMagicLinkMail($email, $token, $code));

        // Registramos la intencion de marketing opt-in (si la marco).
        // No la guardamos como consent todavia; solo cuando confirme la
        // identidad al verify. Lo cacheamos brevemente.
        if ($marketingOptIn) {
            Cache::put('mkt_marketing_optin:' . hash('sha256', $token), true, 1800);
        }

        return ['token' => $token, 'expires_in' => self::TOKEN_TTL_MINUTES * 60];
    }

    /**
     * Consume el magic link (por token o por codigo). Devuelve el
     * MarketplaceUser autenticado o lanza \RuntimeException si invalido.
     */
    public function verify(?string $emailOrNull, ?string $token, ?string $code, Request $request): MarketplaceUser
    {
        $link = null;

        if ($token) {
            $link = MarketplaceUserMagicLink::where('token_hash', hash('sha256', $token))->first();
        } elseif ($code && $emailOrNull) {
            $email = strtolower(trim($emailOrNull));
            $link = MarketplaceUserMagicLink::where('email', $email)
                ->whereNull('consumed_at')
                ->orderByDesc('id')
                ->first();
            if ($link) {
                $link->increment('attempts');
                if ($link->attempts > self::MAX_CODE_ATTEMPTS) {
                    throw new \RuntimeException('Demasiados intentos. Solicita un nuevo codigo.');
                }
                if (!hash_equals($link->code_hash, hash('sha256', $code))) {
                    throw new \RuntimeException('Codigo incorrecto.');
                }
            }
        }

        if (!$link) {
            throw new \RuntimeException('Link o codigo invalido.');
        }
        if ($link->isExpired()) {
            throw new \RuntimeException('El link expiro. Solicita uno nuevo.');
        }
        if ($link->isConsumed()) {
            throw new \RuntimeException('Este link ya fue usado.');
        }

        $email = $link->email;
        $user  = MarketplaceUser::where('email', $email)->first();
        if (!$user) {
            // Registro pasivo al primer verify.
            $user = MarketplaceUser::create([
                'email'             => $email,
                // Default name = parte local del email; el user puede editarlo.
                'name'              => Str::title(Str::before($email, '@')),
                'email_verified_at' => now(),
                'status'            => 'active',
            ]);
            // Preferencias default.
            MarketplaceUserPreference::create([
                'user_id'            => $user->id,
                'email_frequency'    => 'weekly',
                'whatsapp_frequency' => 'off',
            ]);
            // Consent transaccional: implicito al crear cuenta para
            // poder enviarle confirmaciones de pedido, magic links, etc.
            $this->grantConsent($user, 'email', 'transactional', 'registration', $request);
            // Welcome email (transaccional, no requiere consent gating).
            $this->sendWelcomeSilent($user);
        } else {
            // Marcamos verified si no lo estaba (login a cuenta preexistente).
            if (!$user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        }

        // Marketing opt-in solicitado en el form: lo concretamos ahora.
        if ($token && Cache::pull('mkt_marketing_optin:' . hash('sha256', $token))) {
            $this->grantConsent($user, 'email', 'marketing', 'registration', $request);
        }

        // Bloqueamos el link y actualizamos last_login.
        $link->forceFill(['consumed_at' => now()])->save();
        $user->forceFill([
            'last_login_at' => now(),
            'last_seen_at'  => now(),
        ])->save();

        return $user;
    }

    /**
     * Crea una fila de consent (append-only). Si ya existe identico vigente,
     * lo dejamos pasar igual — el costo de una fila extra es nulo y simplifica
     * la auditoria.
     */
    public function grantConsent(MarketplaceUser $user, string $channel, string $purpose, string $source, Request $request): MarketplaceUserConsent
    {
        return MarketplaceUserConsent::create([
            'user_id'    => $user->id,
            'channel'    => $channel,
            'purpose'    => $purpose,
            'granted_at' => now(),
            'source'     => $source,
            'ip'         => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }

    public function revokeConsent(MarketplaceUser $user, string $channel, string $purpose, string $source, Request $request): MarketplaceUserConsent
    {
        return MarketplaceUserConsent::create([
            'user_id'    => $user->id,
            'channel'    => $channel,
            'purpose'    => $purpose,
            'revoked_at' => now(),
            'source'     => $source,
            'ip'         => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }

    /**
     * Login clasico email + password. Lanza ValidationException si falla.
     * Rate-limit por email + IP separado del magic link (5/15min).
     */
    public function loginWithPassword(string $email, string $password, Request $request): MarketplaceUser
    {
        $email = strtolower(trim($email));
        $key   = 'mkt_login:' . sha1($email . '|' . $request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Demasiados intentos. Intenta en 15 minutos.']);
        }

        $user = MarketplaceUser::where('email', $email)->first();
        if (!$user || !$user->password_hash || !Hash::check($password, $user->password_hash)) {
            RateLimiter::hit($key, 900);
            throw ValidationException::withMessages(['password' => 'Email o contraseña incorrectos.']);
        }
        if (!$user->isActive()) {
            throw ValidationException::withMessages(['email' => 'Esta cuenta esta deshabilitada.']);
        }

        RateLimiter::clear($key);
        $user->forceFill([
            'last_login_at' => now(),
            'last_seen_at'  => now(),
        ])->save();
        return $user;
    }

    /**
     * Registro con form completo (vs magic link que crea cuenta pasiva).
     * Si el email YA existe sin password → setea password (recovery flow).
     * Si el email YA existe con password → 422 (que use login normal).
     */
    public function register(array $data, Request $request): MarketplaceUser
    {
        $email = strtolower(trim($data['email']));
        $existing = MarketplaceUser::where('email', $email)->first();
        if ($existing && $existing->password_hash) {
            throw ValidationException::withMessages([
                'email' => 'Ya tienes cuenta. Inicia sesion con tu contraseña.',
            ]);
        }

        $user = $existing ?: new MarketplaceUser();
        $user->fill([
            'email'             => $email,
            'name'              => trim($data['name']),
            'phone'             => !empty($data['phone']) ? preg_replace('/[^\d+]/', '', $data['phone']) : null,
            'password_hash'     => Hash::make($data['password']),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'status'            => 'active',
            'last_login_at'     => now(),
            'last_seen_at'      => now(),
        ])->save();

        // Preferencias default si no existen.
        MarketplaceUserPreference::firstOrCreate(
            ['user_id' => $user->id],
            ['email_frequency' => 'weekly', 'whatsapp_frequency' => 'off']
        );

        // Consent transaccional implicito (necesario para confirmaciones).
        $this->grantConsent($user, 'email', 'transactional', 'registration', $request);
        if (!empty($data['marketing'])) {
            $this->grantConsent($user, 'email', 'marketing', 'registration', $request);
        }

        // Welcome solo si la cuenta NO existia antes (existing es null en este path).
        if (!$existing) {
            $this->sendWelcomeSilent($user);
        }

        return $user;
    }

    /**
     * Login/registro via Google OAuth. Recibe el SocialiteUser ya
     * autenticado y crea o recupera el MarketplaceUser por email.
     *
     * Si el email YA existe → login (sin tocar password si existe).
     * Si NO existe → registro pasivo, sin password (puede setearla
     * desde "Mi cuenta" si quiere combinar metodos).
     */
    public function loginOrRegisterGoogle($googleUser, Request $request): MarketplaceUser
    {
        $email = strtolower(trim((string) $googleUser->getEmail()));
        if (!$email) {
            throw ValidationException::withMessages(['email' => 'Google no devolvio un email valido.']);
        }
        $user = MarketplaceUser::where('email', $email)->first();
        $isNew = !$user;
        if ($isNew) {
            $user = new MarketplaceUser();
        }
        $user->fill([
            'email'             => $email,
            'name'              => $user->name ?: (trim((string) $googleUser->getName()) ?: Str::title(Str::before($email, '@'))),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'status'            => $user->status ?: 'active',
            'last_login_at'     => now(),
            'last_seen_at'      => now(),
        ])->save();

        if ($isNew) {
            MarketplaceUserPreference::firstOrCreate(
                ['user_id' => $user->id],
                ['email_frequency' => 'weekly', 'whatsapp_frequency' => 'off']
            );
            $this->grantConsent($user, 'email', 'transactional', 'google_signin', $request);
            $this->sendWelcomeSilent($user);
        }
        if (!$user->isActive()) {
            throw ValidationException::withMessages(['email' => 'Esta cuenta esta deshabilitada.']);
        }
        return $user;
    }

    /**
     * Envia welcome email — best-effort. Si SMTP cae, no rompe el registro.
     * Transaccional: salta consent gating (es necesario que llegue).
     */
    private function sendWelcomeSilent(MarketplaceUser $user): void
    {
        try {
            Mail::to($user->email)->send(new MarketplaceWelcomeMail($user));
        } catch (\Throwable $e) {
            logger()->warning('Welcome mail failed', ['user_id' => $user->id, 'err' => $e->getMessage()]);
        }
    }

    /** Cambio/seteo de password desde "Mi cuenta". */
    public function setPassword(MarketplaceUser $user, ?string $current, string $new): void
    {
        if ($user->password_hash) {
            if (!$current || !Hash::check($current, $user->password_hash)) {
                throw ValidationException::withMessages(['current_password' => 'Contraseña actual incorrecta.']);
            }
        }
        if (strlen($new) < 8) {
            throw ValidationException::withMessages(['password' => 'La contraseña debe tener al menos 8 caracteres.']);
        }
        $user->forceFill(['password_hash' => Hash::make($new)])->save();
    }
}
