<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente de la API de Nginx Proxy Manager.
 *
 * POR QUE EXISTE
 * --------------
 * En este servidor el 443 lo termina un Nginx Proxy Manager en Docker, no el
 * nginx del sistema. Por eso `certbot` no sirve de nada aqui: no esta
 * instalado, el puerto 80 lo tiene el contenedor y `www-data` no podria
 * ejecutarlo igualmente. Dar de alta un dominio propio exigia entrar al panel
 * a mano, y ese es el paso que se olvida.
 *
 * Esta clase habla con su API (comprobado: responde en 127.0.0.1:81, version
 * 2.13.6) para crear el proxy host y pedir el certificado.
 *
 * ESTA APAGADA POR DEFECTO
 * ------------------------
 * Sin `NPM_EMAIL` y `NPM_PASSWORD` en el `.env`, `estaConfigurado()` devuelve
 * false y nadie llama a nada. Es deliberado: mientras no haya credenciales, el
 * sistema debe decir que el certificado se crea a mano, no intentarlo y
 * fallar.
 *
 * AVISO HONESTO
 * -------------
 * Las llamadas autenticadas NO se han podido probar contra el servidor real
 * porque no tengo las credenciales del panel. Lo verificado es que la API
 * existe, responde y que `/api/tokens` contesta. El resto sigue la API
 * documentada de NPM v2. La primera vez que se use conviene mirar el log.
 */
class NginxProxyManager
{
    /** El destino al que apuntan todos los hosts del proyecto. */
    private const DESTINO_HOST   = '172.17.0.1';
    private const DESTINO_PUERTO = 8088;

    public function estaConfigurado(): bool
    {
        return (bool) config('tenant.npm.email') && (bool) config('tenant.npm.password');
    }

    /**
     * Da de alta el dominio en el proxy y le pide certificado.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function publicarDominio(string $dominio): array
    {
        if (!$this->estaConfigurado()) {
            return ['ok' => false, 'mensaje' => 'NPM sin credenciales en el .env'];
        }

        $token = $this->token();
        if (!$token) {
            return ['ok' => false, 'mensaje' => 'No se pudo autenticar contra NPM'];
        }

        if ($this->yaExiste($token, $dominio)) {
            return ['ok' => true, 'mensaje' => 'El proxy host ya existia'];
        }

        // El certificado primero: el proxy host se crea apuntando a el.
        $certificadoId = $this->pedirCertificado($token, $dominio);
        if (!$certificadoId) {
            return ['ok' => false, 'mensaje' => 'Let\'s Encrypt rechazo el certificado (revisa que el DNS ya apunte aqui)'];
        }

        return $this->crearProxyHost($token, $dominio, $certificadoId)
            ? ['ok' => true, 'mensaje' => 'Proxy host y certificado creados']
            : ['ok' => false, 'mensaje' => 'El certificado se emitio pero fallo el alta del proxy host'];
    }

    private function token(): ?string
    {
        try {
            $r = $this->http()->post('/api/tokens', [
                'identity' => config('tenant.npm.email'),
                'secret'   => config('tenant.npm.password'),
            ]);

            return $r->successful() ? ($r->json('token') ?: null) : null;
        } catch (\Throwable $e) {
            // Nunca se registra la contrasena, solo que fallo.
            Log::warning('NPM: fallo la autenticacion', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function yaExiste(string $token, string $dominio): bool
    {
        try {
            $r = $this->http()->withToken($token)->get('/api/nginx/proxy-hosts');
            if (!$r->successful()) {
                return false;
            }

            foreach ((array) $r->json() as $host) {
                if (in_array($dominio, (array) ($host['domain_names'] ?? []), true)) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('NPM: no se pudo listar proxy hosts', ['error' => $e->getMessage()]);
        }

        return false;
    }

    private function pedirCertificado(string $token, string $dominio): ?int
    {
        try {
            $r = $this->http()->timeout(180)->withToken($token)->post('/api/nginx/certificates', [
                'provider'     => 'letsencrypt',
                'nice_name'    => $dominio,
                'domain_names' => [$dominio],
                'meta'         => [
                    'letsencrypt_email' => config('tenant.ssl_email'),
                    'letsencrypt_agree' => true,
                    'dns_challenge'     => false,
                ],
            ]);

            if (!$r->successful()) {
                Log::warning('NPM: Let\'s Encrypt no emitio el certificado', [
                    'dominio'  => $dominio,
                    'http'     => $r->status(),
                    'respuesta'=> mb_substr($r->body(), 0, 400),
                ]);
                return null;
            }

            return (int) $r->json('id') ?: null;
        } catch (\Throwable $e) {
            Log::warning('NPM: excepcion pidiendo certificado', ['dominio' => $dominio, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function crearProxyHost(string $token, string $dominio, int $certificadoId): bool
    {
        try {
            $r = $this->http()->withToken($token)->post('/api/nginx/proxy-hosts', [
                'domain_names'           => [$dominio],
                'forward_scheme'         => 'http',
                'forward_host'           => self::DESTINO_HOST,
                'forward_port'           => self::DESTINO_PUERTO,
                'certificate_id'         => $certificadoId,
                'ssl_forced'             => true,
                'http2_support'          => true,
                'block_exploits'         => true,
                'allow_websocket_upgrade'=> true,
                'caching_enabled'        => false,
                'hsts_enabled'           => false,
                'hsts_subdomains'        => false,
                'access_list_id'         => 0,
                'advanced_config'        => '',
                'locations'              => [],
                'meta'                   => ['letsencrypt_agree' => true, 'dns_challenge' => false],
            ]);

            if (!$r->successful()) {
                Log::warning('NPM: fallo el alta del proxy host', [
                    'dominio'  => $dominio,
                    'http'     => $r->status(),
                    'respuesta'=> mb_substr($r->body(), 0, 400),
                ]);
            }

            return $r->successful();
        } catch (\Throwable $e) {
            Log::warning('NPM: excepcion creando proxy host', ['dominio' => $dominio, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private function http()
    {
        return Http::baseUrl(rtrim((string) config('tenant.npm.url'), '/'))
            ->timeout(30)
            ->acceptJson();
    }
}
