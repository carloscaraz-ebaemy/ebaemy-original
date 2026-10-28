<?php

namespace App\Console\Commands;

use App\Services\System\ExternalSellerRegistrar;
use Illuminate\Console\Command;

/**
 * Alta manual de una tienda de otro sistema (PRO 9) como vendedor del
 * marketplace. Lo normal es que la tienda se registre sola desde PRO 9
 * (botón "Publicar en ebaemy.com"); esto queda para casos puntuales o para
 * renovar el secreto de una tienda.
 *
 *   php artisan marketplace:external-seller importaloya.devaemy.com "Importa Lo Ya" --webhook=https://importaloya.devaemy.com
 *   php artisan marketplace:external-seller importaloya.devaemy.com "Importa Lo Ya" --approve
 *
 * Muestra la clave y el secreto que se configuran en la tienda.
 */
class MarketplaceExternalSellerCreate extends Command
{
    protected $signature = 'marketplace:external-seller
                            {fqdn : Dominio de la tienda en el otro sistema}
                            {name : Nombre público de la tienda}
                            {--webhook= : URL base del sistema del vendedor para avisarle pedidos}
                            {--approve : Dejarla aprobada (por defecto queda por aprobar)}';

    protected $description = 'Registra (o renueva el secreto de) una tienda PRO 9 como vendedor externo del marketplace';

    public function handle(ExternalSellerRegistrar $registrar): int
    {
        try {
            $result = $registrar->register(
                $this->argument('fqdn'),
                $this->argument('name'),
                $this->option('webhook'),
                [],
                $this->option('approve') ? 'approved' : 'pending'
            );
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $seller = $result['seller'];
        if ($this->option('approve') && $seller->status !== 'approved') {
            $seller->changeStatus('approved');
        }

        $this->info(($result['created'] ? 'Vendedor creado' : 'Secreto renovado') . " #{$seller->id} ({$seller->fqdn}) — {$seller->statusLabel()}");
        $this->line('  Clave:   ' . $seller->api_key);
        $this->line('  Secreto: ' . $result['secret']);
        $this->warn('El secreto no se vuelve a mostrar.');
        return self::SUCCESS;
    }
}
