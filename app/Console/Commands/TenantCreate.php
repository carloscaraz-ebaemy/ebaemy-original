<?php

namespace App\Console\Commands;

use Hyn\Tenancy\Contracts\Repositories\HostnameRepository;
use Hyn\Tenancy\Contracts\Repositories\WebsiteRepository;
use Hyn\Tenancy\Models\Hostname;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;

/**
 * Crear un tenant nuevo desde la línea de comandos.
 *
 * Uso:
 *   php artisan tenant:create miempresa
 *   php artisan tenant:create miempresa --domain=miempresa.com
 */
class TenantCreate extends Command
{
    protected $signature = 'tenant:create
                            {subdomain : Subdominio del tenant (ej: miempresa)}
                            {--domain= : Dominio base (default: config)}
                            {--run-migrations : Ejecutar migraciones del tenant}';

    protected $description = 'Crear un nuevo tenant con su BD y hostname';

    public function handle(): int
    {
        $subdomain  = $this->argument('subdomain');
        $baseDomain = $this->option('domain') ?: config('tenancy.hostname.default', 'ebaemy.test');
        $fqdn       = $subdomain . '.' . $baseDomain;

        // Verificar que no exista
        if (Hostname::where('fqdn', $fqdn)->exists()) {
            $this->error("El hostname '{$fqdn}' ya existe.");
            return 1;
        }

        $this->info("Creando tenant: {$fqdn}");

        // Crear website (BD)
        $website = new Website;
        $website->uuid = $subdomain;
        app(WebsiteRepository::class)->create($website);
        $this->info("  BD creada: {$website->uuid}");

        // Crear hostname
        $hostname = new Hostname;
        $hostname->fqdn = $fqdn;
        $hostname->website_id = $website->id;
        app(HostnameRepository::class)->create($hostname);
        $this->info("  Hostname: {$fqdn}");

        // Migraciones
        if ($this->option('run-migrations')) {
            $this->call('tenancy:migrate', ['--website_id' => $website->id]);
        }

        $this->info("Tenant '{$subdomain}' creado exitosamente.");
        $this->table(['Campo', 'Valor'], [
            ['FQDN', $fqdn],
            ['Website ID', $website->id],
            ['UUID', $website->uuid],
            ['Hostname ID', $hostname->id],
        ]);

        return 0;
    }
}
