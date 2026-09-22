<?php

namespace App\Listeners\Security;

use Hyn\Tenancy\Environment;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

/**
 * Bitacora de autenticacion.
 *
 * Escribe un registro en `login_events` por cada intento de acceso. Es la unica
 * fuente de datos del modulo 5 (ingreso no autorizado) y del modulo 6 (horarios)
 * del agente de seguridad; el agente NO escribe aqui, solo lee.
 *
 * Reglas:
 *  - Nunca puede romper el login: todo va envuelto en try/catch.
 *  - No se guarda la contrasena ni ningun credencial, solo el identificador.
 *  - La geolocalizacion NO se resuelve aqui (seria una llamada HTTP en cada
 *    login). La resuelve el agente al escanear, con cache en disco.
 */
class RecordLoginEvent
{
    public function handleLogin(Login $event): void
    {
        $this->write('success', $event->guard, $event->user?->getAuthIdentifier(), $this->emailOf($event->user));
    }

    public function handleFailed(Failed $event): void
    {
        $this->write('failed', $event->guard, $event->user?->getAuthIdentifier(), $this->credentialIdentifier($event->credentials));
    }

    public function handleLockout(Lockout $event): void
    {
        $credentials = method_exists($event->request, 'only')
            ? $event->request->only(['email', 'username', 'user'])
            : [];

        $this->write('lockout', null, null, $this->credentialIdentifier($credentials));
    }

    public function handleLogout(Logout $event): void
    {
        $this->write('logout', $event->guard, $event->user?->getAuthIdentifier(), $this->emailOf($event->user));
    }

    public function subscribe($events): array
    {
        return [
            Login::class   => 'handleLogin',
            Failed::class  => 'handleFailed',
            Lockout::class => 'handleLockout',
            Logout::class  => 'handleLogout',
        ];
    }

    // ── Interno ───────────────────────────────────────────────────────────────

    private function write(string $result, ?string $guard, $userId, ?string $email): void
    {
        try {
            $model = $this->resolveModel();
            if ($model === null) return;

            $model::create([
                'user_id'    => is_numeric($userId) ? (int) $userId : null,
                'guard'      => $guard ? substr($guard, 0, 40) : null,
                'email'      => $email ? substr($email, 0, 191) : null,
                'result'     => $result,
                'ip_address' => request()->ip(),
                'user_agent' => substr(request()->userAgent() ?? '', 0, 500),
                'url'        => substr(request()->fullUrl(), 0, 255),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Un fallo de bitacora jamas debe impedir (ni delatar) un login.
            Log::warning('[RecordLoginEvent] No se pudo registrar el evento.', [
                'result' => $result,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Elige la tabla: la del tenant si hay hostname identificado, la del
     * sistema si el acceso es al panel de superadmin.
     */
    private function resolveModel(): ?string
    {
        try {
            $tenant = app(Environment::class)->tenant();
        } catch (\Throwable $e) {
            $tenant = null;
        }

        if ($tenant) {
            return \App\Models\Tenant\LoginEvent::class;
        }

        return \App\Models\System\LoginEvent::class;
    }

    private function emailOf($user): ?string
    {
        if (!$user) return null;

        foreach (['email', 'username', 'user'] as $attribute) {
            if (!empty($user->{$attribute})) {
                return (string) $user->{$attribute};
            }
        }

        return null;
    }

    /**
     * Saca el identificador intentado sin arrastrar nunca la contrasena.
     */
    private function credentialIdentifier(array $credentials): ?string
    {
        foreach (['email', 'username', 'user'] as $key) {
            if (!empty($credentials[$key]) && is_string($credentials[$key])) {
                return $credentials[$key];
            }
        }

        return null;
    }
}
