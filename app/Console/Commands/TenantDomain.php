<?php

namespace App\Console\Commands;

use App\Models\System\Client;
use App\Models\System\DomainVerification;
use App\Services\System\DomainVerificationService;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Console\Command;

/**
 * Dominio propio de un tenant, de punta a punta, desde la consola.
 *
 * El flujo entero ya existia —servicio, job de DNS, controlador de SuperAdmin
 * y columnas en `hostnames`— pero no habia ninguna pantalla que lo invocara,
 * asi que estaba construido y sin usar: `domain_verifications` tenia 0 filas.
 * Esto lo deja operable hoy, sin esperar a la interfaz.
 *
 *   php artisan tenant:domain listar
 *   php artisan tenant:domain alta      alasitas grupoalasitas.com
 *   php artisan tenant:domain verificar grupoalasitas.com
 *   php artisan tenant:domain activar   grupoalasitas.com
 *
 * El orden importa: `activar` da de alta el hostname y exige que el dominio
 * este verificado. Y despues de activar hace falta UNA cosa mas que no se
 * puede hacer desde aqui — el certificado y el proxy host en Nginx Proxy
 * Manager— porque el 443 lo termina el, no esta aplicacion. El comando lo
 * recuerda al terminar.
 */
class TenantDomain extends Command
{
    protected $signature = 'tenant:domain
                            {accion : listar | alta | verificar | activar}
                            {tenant? : subdominio del tenant (solo en `alta`), ej. alasitas}
                            {dominio? : el dominio propio, ej. grupoalasitas.com}
                            {--metodo=dns_cname : dns_cname o dns_txt}';

    protected $description = 'Gestiona el dominio propio de un tenant (alta, verificacion y activacion)';

    public function handle(DomainVerificationService $servicio): int
    {
        return match ($this->argument('accion')) {
            'listar'    => $this->listar(),
            'alta'      => $this->alta($servicio),
            'verificar' => $this->verificar($servicio),
            'activar'   => $this->activar($servicio),
            default     => $this->fallo('Accion no reconocida. Usa: listar, alta, verificar o activar.'),
        };
    }

    private function listar(): int
    {
        $filas = [];

        foreach (Hostname::orderBy('fqdn')->get() as $h) {
            $filas[] = [
                $h->fqdn,
                $h->domain_type ?: 'subdominio',
                $h->ssl_status ?: '—',
                $h->website_id,
            ];
        }
        $this->table(['Hostname', 'Tipo', 'SSL', 'Website'], $filas);

        $verif = DomainVerification::orderByDesc('id')->get();
        if ($verif->isEmpty()) {
            $this->line('No hay verificaciones de dominio registradas.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Dominio', 'Metodo', 'Estado', 'Intentos', 'Caduca'],
            $verif->map(fn ($v) => [
                $v->domain,
                $v->method,
                $v->status,
                $v->attempts ?? 0,
                optional($v->expires_at)->format('Y-m-d') ?: '—',
            ])->all()
        );

        return self::SUCCESS;
    }

    private function alta(DomainVerificationService $servicio): int
    {
        $sub     = (string) $this->argument('tenant');
        $dominio = (string) $this->argument('dominio');

        if ($sub === '' || $dominio === '') {
            return $this->fallo('Uso: tenant:domain alta <tenant> <dominio>');
        }

        $hostname = Hostname::where('fqdn', 'like', $sub . '.%')->first();
        if (!$hostname) {
            return $this->fallo("No encuentro ningun tenant cuyo subdominio empiece por '{$sub}'.");
        }

        $client = Client::where('hostname_id', $hostname->id)->first();
        if (!$client) {
            return $this->fallo("El hostname '{$hostname->fqdn}' no tiene un client asociado.");
        }

        try {
            $v = $servicio->createVerification($client, $dominio, $this->option('metodo'));
        } catch (\Throwable $e) {
            return $this->fallo($e->getMessage());
        }

        $this->info("Verificacion creada para {$v->domain} (tenant: {$hostname->fqdn}).");
        $this->newLine();
        $this->line('El cliente debe crear este registro en el DNS de su dominio:');

        $i = $v->getVerificationInstructions();
        $this->table(['Tipo', 'Host', 'Valor'], [[
            $i['type'] ?? '?',
            $i['host'] ?? $v->domain,
            $i['value'] ?? ($i['instructions'] ?? '?'),
        ]]);

        $this->line("Cuando lo tenga puesto:  php artisan tenant:domain verificar {$v->domain}");
        $this->comment('El DNS tarda en propagarse; si falla a la primera, reintenta en unos minutos.');

        return self::SUCCESS;
    }

    private function verificar(DomainVerificationService $servicio): int
    {
        $v = $this->buscarVerificacion();
        if (!$v) {
            return self::FAILURE;
        }

        $this->line("Consultando el DNS de {$v->domain}...");

        if ($servicio->verify($v)) {
            $this->info('Verificado.');
            $this->line("Siguiente paso:  php artisan tenant:domain activar {$v->domain}");
            return self::SUCCESS;
        }

        $this->warn('Todavia no verifica. El registro no esta puesto o el DNS no ha propagado.');
        $i = $v->getVerificationInstructions();
        $this->line('Se espera: ' . ($i['instructions'] ?? '?'));

        return self::FAILURE;
    }

    private function activar(DomainVerificationService $servicio): int
    {
        $v = $this->buscarVerificacion();
        if (!$v) {
            return self::FAILURE;
        }

        try {
            $hostname = $servicio->activateDomain($v);
        } catch (\Throwable $e) {
            return $this->fallo($e->getMessage());
        }

        $this->info("Hostname dado de alta: {$hostname->fqdn} (website {$hostname->website_id}).");
        $this->newLine();
        $this->warn('FALTA UN PASO QUE NO SE PUEDE HACER DESDE AQUI.');
        $this->line('El 443 lo termina Nginx Proxy Manager, no esta aplicacion. En su panel');
        $this->line('(puerto 81) hay que crear un Proxy Host con estos valores — son los mismos');
        $this->line('que usa el comodin *.ebaemy.com, leidos de su propia configuracion:');
        $this->newLine();
        $this->table(['Campo', 'Valor'], [
            ['Domain Names',     $hostname->fqdn],
            ['Scheme',           'http'],
            ['Forward Hostname', '172.17.0.1'],
            ['Forward Port',     '8088'],
            ['Block Exploits',   'si'],
            ['Websockets',       'si'],
            ['SSL',              "Request a new SSL Certificate (Let's Encrypt)"],
            ['Force SSL',        'si'],
            ['HTTP/2 Support',   'si'],
        ]);
        $this->line('El certificado se emite por desafio HTTP, asi que el DNS del dominio tiene que');
        $this->line('apuntar YA a este servidor antes de pedirlo.');
        $this->newLine();
        $this->line('Y para que la tienda no quede accesible por dos direcciones a la vez:');
        $this->line("  - marca {$hostname->fqdn} como principal (is_primary)");
        $this->line('  - marca el subdominio viejo con redirect_to_primary');
        $this->line('Sin eso las dos sirven el mismo catalogo y compiten entre ellas en Google.');

        return self::SUCCESS;
    }

    private function buscarVerificacion(): ?DomainVerification
    {
        $dominio = (string) $this->argument('dominio') ?: (string) $this->argument('tenant');

        if ($dominio === '') {
            $this->error('Falta el dominio.');
            return null;
        }

        $v = DomainVerification::where('domain', $dominio)->orderByDesc('id')->first();
        if (!$v) {
            $this->error("No hay ninguna verificacion para '{$dominio}'. Crea una con `tenant:domain alta`.");
            return null;
        }

        return $v;
    }

    private function fallo(string $mensaje): int
    {
        $this->error($mensaje);
        return self::FAILURE;
    }
}
