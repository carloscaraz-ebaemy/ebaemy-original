<?php

namespace App\Services\System;

use App\Models\System\MarketplaceExternalSeller;
use App\Models\System\SystemAdminNotification;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Alta de una tienda externa (PRO 9) como vendedor del marketplace. La usan
 * la API de plataforma (botón "Publicar en ebaemy.com" en PRO 9) y el comando
 * marketplace:external-seller.
 *
 * Crea un `hostnames` SIN website: el marketplace agrupa todo por hostname_id
 * y los procesos que entran a la BD del tenant ya saltan los que no tienen
 * website. domain_type = 'external' lo deja fuera de las herramientas de SSL.
 */
class ExternalSellerRegistrar
{
    /**
     * Registra la tienda o, si ya existía, renueva su secreto y sus datos.
     * Una tienda rechazada que vuelve a pedirlo pasa otra vez a "por aprobar";
     * una suspendida sigue suspendida (eso lo levanta el administrador).
     *
     * @return array{seller: MarketplaceExternalSeller, secret: string, created: bool}
     */
    public function register(string $fqdn, string $name, ?string $webhookUrl, array $contact = [], string $initialStatus = 'pending'): array
    {
        $fqdn = strtolower(trim($fqdn));
        if (!preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)+$/', $fqdn)) {
            throw new \InvalidArgumentException('Dominio inválido');
        }

        $existingHost = Hostname::where('fqdn', $fqdn)->first();
        if ($existingHost && $existingHost->website_id) {
            throw new \InvalidArgumentException('Ese dominio ya es una tienda de ebaemy');
        }

        $secret = Str::random(48);
        $data = array_filter([
            'name'          => Str::limit($name, 160, ''),
            'webhook_url'   => $webhookUrl,
            'contact_phone' => isset($contact['phone']) ? Str::limit((string) $contact['phone'], 30, '') : null,
            'contact_email' => isset($contact['email']) ? Str::limit((string) $contact['email'], 150, '') : null,
        ], fn ($v) => $v !== null && $v !== '');

        $seller = MarketplaceExternalSeller::where('fqdn', $fqdn)->first();
        if ($seller) {
            $seller->update($data + [
                'api_secret' => $secret,
                'status'     => $seller->status === 'rejected' ? 'pending' : $seller->status,
            ]);
            if ($seller->wasChanged('status')) {
                $this->notifyAdmin($seller);
            }
            return ['seller' => $seller, 'secret' => $secret, 'created' => false];
        }

        $seller = DB::connection('system')->transaction(function () use ($existingHost, $fqdn, $data, $secret, $initialStatus) {
            $hostname = $existingHost ?: new Hostname();
            $hostname->forceFill([
                'fqdn'        => $fqdn,
                'website_id'  => null,
                'domain_type' => 'external',
                'force_https' => false,
            ])->save();

            return MarketplaceExternalSeller::create($data + [
                'hostname_id' => $hostname->id,
                'source'      => 'pro9',
                'fqdn'        => $fqdn,
                'api_key'     => 'mk_' . Str::lower(Str::random(32)),
                'api_secret'  => $secret,
                'status'      => $initialStatus,
            ]);
        });

        if ($seller->status === 'pending') {
            $this->notifyAdmin($seller);
        }

        return ['seller' => $seller, 'secret' => $secret, 'created' => true];
    }

    private function notifyAdmin(MarketplaceExternalSeller $seller): void
    {
        try {
            SystemAdminNotification::notify(
                'external_seller',
                'Marketplace: ' . $seller->name . ' pide vender en ebaemy',
                $seller->fqdn . ($seller->contact_phone ? ' · WhatsApp ' . $seller->contact_phone : ''),
                '/admin/marketplace/tiendas-externas',
                '🏪',
                MarketplaceExternalSeller::class,
                $seller->id
            );
        } catch (\Throwable $e) {
            Log::warning('[ExternalSeller] no se pudo avisar al administrador', ['id' => $seller->id, 'error' => $e->getMessage()]);
        }
    }
}
