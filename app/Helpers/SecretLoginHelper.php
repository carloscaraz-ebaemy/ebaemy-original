<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Hyn\Tenancy\Environment;
use App\Models\System\Client;
use App\Models\Tenant\User;
use Hyn\Tenancy\Contracts\CurrentHostname;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Exception;


class SecretLoginHelper
{
    /** Tiempo máximo de validez del secret login en minutos */
    private const SECRET_LOGIN_EXPIRY_MINUTES = 15;

    /**
     *
     * @return int
     */
    public function getHostnameId()
    {
        return app(CurrentHostname::class)->id;
    }


    /**
     *
     * @param  Client $client
     * @return void
     */
    public function setTenantConnection(Client $client)
    {
        $tenancy = app(Environment::class);
        $tenancy->tenant($client->hostname->website);
    }


    /**
     * Genera un hash HMAC-SHA256 firmado con la clave de la aplicación.
     *
     * @param  User $user
     * @param  Client $client
     * @return string
     */
    public function createHash(User $user, Client $client)
    {
        return hash_hmac('sha256', $this->createKeyString($user, $client), config('app.key'));
    }


    /**
     *
     * @param  User $user
     * @param  Client $client
     * @return string
     */
    public function createKeyString(User $user, Client $client)
    {
        return implode('|', [
            'client_id' => $client->id,
            'client_number' => $client->number,
            'fqdn' => $client->hostname->fqdn,
            'secret_login_time' => $user->secret_login_time,
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_api_token' => $user->api_token,
        ]);
    }


    /**
     * Verifica si el secret_login_time no ha expirado (máximo 15 minutos).
     *
     * @param  User $user
     * @return bool
     */
    public function isExpired(User $user): bool
    {
        if (empty($user->secret_login_time)) {
            return true;
        }

        $loginTime = Carbon::parse($user->secret_login_time);
        $expiresAt = $loginTime->addMinutes(self::SECRET_LOGIN_EXPIRY_MINUTES);

        if (Carbon::now()->greaterThan($expiresAt)) {
            Log::warning('[SecretLogin] Token expirado.', [
                'user_id' => $user->id,
                'secret_login_time' => $user->secret_login_time,
                'expired_at' => $expiresAt->toDateTimeString(),
            ]);
            return true;
        }

        return false;
    }


    /**
     *
     * @param  User $user
     * @param   $time
     * @return User
     */
    public function updateSecretLoginTime(User &$user, $time = null)
    {
        $user->secret_login_time = $time;
        $user->save();
    }


    /**
     *
     * @return User
     */
    public function getFirstUser()
    {
        return User::firstOrFail();
    }


    /**
     *
     * @param  int $client_id
     * @return Client
     */
    public function getClient($client_id)
    {
        return Client::findOrFail($client_id);
    }


    /**
     *
     * @param  int $hostname_id
     * @return Client
     */
    public function getClientByHostname($hostname_id)
    {
        return Client::where('hostname_id', $hostname_id)->firstOrFail();
    }


    /**
     *
     * @param  string $fqdn
     * @param  string $key
     * @return string
     */
    public function redirectUrl($fqdn, $key)
    {
        $protocol = config('tenant.force_https') ? 'https' : 'http';

        return "{$protocol}://".$fqdn."/check-secret-login/{$key}";
    }


    /**
     *
     * @param  string $message
     * @return void
     */
    public function setErrorLog($message)
    {
        Log::error($message);
    }


    /**
     *
     * @param  User $user
     * @return void
     */
    public function loginById(User $user)
    {
        Auth::loginUsingId($user->id);
    }


    /**
     *
     * @param  string $message
     * @return void
     */
    public function isUnauthorized()
    {
        $this->setErrorLog('Error al validar secret login: El hash es inválido.');

        abort(401);
    }

}