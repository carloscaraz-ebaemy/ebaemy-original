<?php

namespace App\Jobs;

use Hyn\Tenancy\Models\Hostname;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Certificado SSL de un dominio propio.
 *
 * LO QUE HACIA ANTES, Y POR QUE NO PODIA FUNCIONAR
 * ------------------------------------------------
 * Ejecutaba `certbot certonly --nginx` y luego `nginx -s reload`. Esta escrito
 * para una maquina donde el nginx del sistema termina el TLS. Aqui no es asi,
 * y se comprobo el 2026-10-12 en el servidor:
 *
 *   - `certbot` NO esta instalado.
 *   - El puerto 80, por donde llega el desafio HTTP de Let's Encrypt, lo tiene
 *     un contenedor Docker (Nginx Proxy Manager). El nginx del host no escucha
 *     en el 80: solo en el 8088, detras del proxy.
 *   - `www-data`, que es quien corre la cola, no puede ejecutar certbot ni
 *     recargar el nginx del sistema.
 *
 * Asi que el job fallaba siempre, y ademas fallaba en SILENCIO para quien
 * miraba la pantalla: la UI decia «Dominio verificado y activado exitosamente»
 * y el certificado no existia. El dominio quedaba dado de alta y sin servir
 * por HTTPS.
 *
 * LO QUE HACE AHORA
 * -----------------
 * No finge. Deja el hostname en `ssl_status = manual_required` y registra en
 * el log los valores EXACTOS que hay que poner en el panel de Nginx Proxy
 * Manager, leidos de la configuracion del comodin que ya funciona. Es
 * deliberado que no intente nada mas: emitir el certificado requiere acceso al
 * proxy, y un job que lo intenta y falla es peor que uno que dice la verdad.
 *
 * Para automatizarlo haria falta un token de la API de Nginx Proxy Manager en
 * el `.env`; mientras no exista, esto es lo correcto.
 */
class GenerateSslCertificate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(protected int $hostnameId) {}

    public function handle(): void
    {
        $hostname = Hostname::find($this->hostnameId);
        if (!$hostname) {
            return;
        }

        try {
            $hostname->ssl_status = 'manual_required';
            $hostname->save();
        } catch (\Throwable $e) {
            // Las columnas de SSL podrian no existir en instalaciones viejas.
        }

        Log::warning('Dominio activado SIN certificado: hay que crearlo a mano en Nginx Proxy Manager', [
            'dominio'          => $hostname->fqdn,
            'panel'            => 'puerto 81 del servidor',
            'scheme'           => 'http',
            'forward_hostname' => '172.17.0.1',
            'forward_port'     => 8088,
            'ssl'              => "Request a new SSL Certificate (Let's Encrypt)",
            'force_ssl'        => true,
            'http2'            => true,
            'aviso'            => 'El desafio es HTTP: el DNS del dominio debe apuntar ya a este servidor antes de pedir el certificado.',
        ]);
    }
}
