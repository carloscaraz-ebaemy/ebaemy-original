<?php

namespace App\Console\Commands;

use App\Models\System\Client;
use App\Services\System\MarketplaceListingSyncService;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pone al día el nombre y el logo con que cada tienda aparece en el marketplace.
 *
 * `marketplace_listings.tenant_name` se copia en el momento de publicar el
 * anuncio y despues no se vuelve a mirar. Si el vendedor configura su nombre
 * comercial mas tarde --o lo corrige-- el marketplace sigue enseñando el que
 * habia entonces. Visto en produccion el 2026-09-25: una tienda anunciada como
 * «MONZON ANDERSON LIA AURORA», que es el nombre del titular, teniendo
 * «LIA DECORACIONES» configurado; y otra con dos nombres a la vez, uno de ellos
 * el «Facturación Electrónica» que viene de fabrica.
 *
 * No resincroniza el catalogo: toca solo esas dos columnas, asi que es barato y
 * se puede correr a menudo.
 *
 *   php artisan marketplace:refresh-branding
 *   php artisan marketplace:refresh-branding --tenant=ebaemy_lia
 *   php artisan marketplace:refresh-branding --dry-run
 */
class MarketplaceRefreshBranding extends Command
{
    protected $signature = 'marketplace:refresh-branding
        {--tenant= : UUID de un website concreto (por defecto: todos)}
        {--dry-run : Solo informa de lo que cambiaria, sin escribir}';

    protected $description = 'Actualiza el nombre y el logo de las tiendas en los anuncios del marketplace';

    public function handle(MarketplaceListingSyncService $sync): int
    {
        $uuid = $this->option('tenant');
        $seco = (bool) $this->option('dry-run');

        $websites = $uuid ? Website::where('uuid', $uuid)->get() : Website::all();

        if ($websites->isEmpty()) {
            $this->error('No hay websites que revisar.');

            return self::FAILURE;
        }

        $env = app(Environment::class);
        $tocados = 0;
        $tiendas = 0;

        foreach ($websites as $website) {
            $fqdn = optional($website->hostnames()->first())->fqdn;
            if (!$fqdn) {
                continue;
            }

            // Sin anuncios publicados no hay nada que corregir, y asi evitamos
            // activar el tenant para nada.
            $anuncios = DB::connection('system')->table('marketplace_listings')
                ->where('tenant_fqdn', $fqdn)->count();
            if (!$anuncios) {
                continue;
            }

            $env->tenant($website);

            $client = Client::where('hostname_id', optional($website->hostnames()->first())->id)->first()
                ?: Client::where('name', DB::connection('tenant')->table('companies')->value('name'))->first();

            if (!$client) {
                $this->warn("  {$fqdn}: sin cliente en la base del sistema, se omite");
                continue;
            }

            $actuales = DB::connection('system')->table('marketplace_listings')
                ->where('tenant_fqdn', $fqdn)->distinct()->pluck('tenant_name');

            if ($seco) {
                // En seco no se escribe: se resuelve el nombre y se compara.
                $res = ['nombre' => null, 'cambiados' => 0];
                $ref = new \ReflectionMethod($sync, 'resolveTenantBranding');
                $ref->setAccessible(true);
                [$nombre] = $ref->invoke($sync, $fqdn, $client);
                $distinto = $actuales->count() > 1 || ($actuales->first() !== $nombre);
                if ($distinto) {
                    $tiendas++;
                    $this->line("  <fg=yellow>cambiaria</> {$fqdn}");
                    $this->line("      ahora:   " . $actuales->implode(' | '));
                    $this->line("      pasaria: {$nombre}");
                }
                continue;
            }

            $res = $sync->refreshBranding($fqdn, $client);

            if ($res['cambiados'] > 0) {
                $tiendas++;
                $tocados += $res['cambiados'];
                $this->line("  <fg=green>actualizado</> {$fqdn}");
                $this->line("      antes:  " . $actuales->implode(' | '));
                $this->line("      ahora:  {$res['nombre']}  ({$res['cambiados']} anuncios)");
            }
        }

        $this->newLine();
        $this->info($seco
            ? "Cambiarian {$tiendas} tiendas."
            : "Listo: {$tiendas} tiendas, {$tocados} anuncios actualizados.");

        return self::SUCCESS;
    }
}
