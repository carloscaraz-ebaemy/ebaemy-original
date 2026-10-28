<?php

namespace App\Console\Commands;

use App\Models\System\MarketplaceExternalSeller;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Da de alta una tienda de otro sistema (PRO 9) como vendedor del marketplace
 * y muestra la clave y el secreto que se pegan en esa tienda (Configuración →
 * Canales → Marketplace ebaemy.com).
 *
 *   php artisan marketplace:external-seller importaloya.devaemy.com "Importa Lo Ya" --webhook=https://importaloya.devaemy.com
 *   php artisan marketplace:external-seller importaloya.devaemy.com --rotate   (nuevo secreto)
 *
 * Crea un `hostnames` SIN website: el marketplace agrupa todo por hostname_id
 * y los procesos que entran a la BD del tenant ya saltan los que no tienen
 * website. domain_type = 'external' lo deja fuera de las herramientas de SSL.
 */
class MarketplaceExternalSellerCreate extends Command
{
    protected $signature = 'marketplace:external-seller
                            {fqdn : Dominio de la tienda en el otro sistema}
                            {name? : Nombre público de la tienda}
                            {--webhook= : URL base del sistema del vendedor para avisarle pedidos}
                            {--rotate : Generar un secreto nuevo para un vendedor existente}';

    protected $description = 'Registra una tienda PRO 9 como vendedor externo del marketplace';

    public function handle(): int
    {
        $fqdn = strtolower(trim($this->argument('fqdn')));
        if (!preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)+$/', $fqdn)) {
            $this->error('Dominio inválido');
            return self::FAILURE;
        }

        $seller = MarketplaceExternalSeller::where('fqdn', $fqdn)->first();

        if ($seller) {
            if (!$this->option('rotate')) {
                $this->warn("Ya existe como vendedor #{$seller->id}. Usa --rotate para generar un secreto nuevo.");
                return self::FAILURE;
            }
            $secret = Str::random(48);
            $seller->update(['api_secret' => $secret] + array_filter(['webhook_url' => $this->option('webhook')]));
            return $this->printCredentials($seller, $secret);
        }

        $name = $this->argument('name');
        if (!$name) {
            $this->error('Falta el nombre de la tienda');
            return self::FAILURE;
        }

        $existing = Hostname::where('fqdn', $fqdn)->first();
        if ($existing && $existing->website_id) {
            $this->error('Ese dominio ya es un tenant de ebaemy');
            return self::FAILURE;
        }

        $sub = strtok($fqdn, '.');
        $clash = Hostname::where('fqdn', 'like', $sub . '.%')->whereNotNull('website_id')->exists();
        if ($clash) {
            $this->warn("Ojo: ya hay un tenant de ebaemy con el subdominio '{$sub}'. La página /marketplace/tienda/{$sub} mostrará esa tienda, no esta.");
        }

        $secret = Str::random(48);

        $seller = DB::connection('system')->transaction(function () use ($existing, $fqdn, $name, $secret) {
            $hostname = $existing ?: new Hostname();
            $hostname->forceFill([
                'fqdn'        => $fqdn,
                'website_id'  => null,
                'domain_type' => 'external',
                'force_https' => false,
            ])->save();

            return MarketplaceExternalSeller::create([
                'hostname_id' => $hostname->id,
                'source'      => 'pro9',
                'fqdn'        => $fqdn,
                'name'        => $name,
                'api_key'     => 'mk_' . Str::lower(Str::random(32)),
                'api_secret'  => $secret,
                'webhook_url' => $this->option('webhook'),
                'is_active'   => true,
            ]);
        });

        return $this->printCredentials($seller, $secret);
    }

    private function printCredentials(MarketplaceExternalSeller $seller, string $secret): int
    {
        $this->info("Vendedor #{$seller->id} ({$seller->fqdn}) listo. Pega esto en la tienda:");
        $this->line('  URL:     ' . rtrim(config('app.url'), '/'));
        $this->line('  Clave:   ' . $seller->api_key);
        $this->line('  Secreto: ' . $secret);
        $this->warn('El secreto no se vuelve a mostrar.');
        return self::SUCCESS;
    }
}
