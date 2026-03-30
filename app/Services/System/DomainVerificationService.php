<?php

namespace App\Services\System;

use App\Jobs\VerifyDomainDns;
use App\Models\System\Client;
use App\Models\System\DomainVerification;
use Hyn\Tenancy\Contracts\Repositories\HostnameRepository;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Support\Facades\Log;

/**
 * DomainVerificationService — Gestión de dominios personalizados.
 *
 * Flujo:
 *  1. createVerification() → genera token y despacha job
 *  2. Job VerifyDomainDns verifica DNS
 *  3. Si verificado → activateDomain() crea hostname
 */
class DomainVerificationService
{
    /**
     * Crear verificación para un dominio personalizado.
     *
     * @param Client $client  El client que quiere agregar el dominio
     * @param string $domain  El dominio a verificar (ej: tienda.cliente.com)
     * @param string $method  Método de verificación: dns_cname | dns_txt
     * @return DomainVerification
     */
    public function createVerification(Client $client, string $domain, string $method = 'dns_cname'): DomainVerification
    {
        $domain = $this->cleanDomain($domain);

        // Verificar que el dominio no esté ya registrado
        $existingHostname = Hostname::where('fqdn', $domain)->first();
        if ($existingHostname) {
            throw new \RuntimeException("El dominio '{$domain}' ya está registrado en el sistema.");
        }

        // Verificar que no haya una verificación pendiente para este dominio
        $existing = DomainVerification::where('domain', $domain)
            ->whereIn('status', ['pending', 'verified'])
            ->first();
        if ($existing) {
            return $existing; // Retornar la existente
        }

        $verification = DomainVerification::create([
            'hostname_id'        => $client->hostname_id,
            'domain'             => $domain,
            'method'             => $method,
            'verification_token' => DomainVerification::generateToken(),
            'status'             => DomainVerification::STATUS_PENDING,
            'expires_at'         => now()->addDays(7),
        ]);

        // Despachar job de verificación (con delay para dar tiempo al usuario de configurar DNS)
        VerifyDomainDns::dispatch($verification->id)->delay(now()->addMinutes(5));

        return $verification;
    }

    /**
     * Verificar un dominio manualmente (sin esperar el job).
     */
    public function verify(DomainVerification $verification): bool
    {
        $job = new VerifyDomainDns($verification->id);
        $job->handle();

        $verification->refresh();
        return $verification->isVerified();
    }

    /**
     * Activar un dominio verificado: crear hostname y asociar al website del client.
     */
    public function activateDomain(DomainVerification $verification): ?Hostname
    {
        if (!$verification->isVerified()) {
            throw new \RuntimeException('El dominio no ha sido verificado.');
        }

        // Obtener el website del client a través del hostname original
        $originalHostname = $verification->hostname;
        if (!$originalHostname || !$originalHostname->website_id) {
            throw new \RuntimeException('No se encontró el website asociado.');
        }

        // Crear nuevo hostname para el dominio custom
        $hostname = new Hostname();
        $hostname->fqdn       = $verification->domain;
        $hostname->website_id = $originalHostname->website_id;

        // Si el modelo soporta los campos nuevos
        if (method_exists($hostname, 'getFillable') || property_exists($hostname, 'domain_type')) {
            $hostname->domain_type = 'custom';
            $hostname->ssl_status  = 'pending';
        }

        app(HostnameRepository::class)->create($hostname);

        Log::info("Custom domain activated: {$verification->domain}", [
            'hostname_id' => $hostname->id,
            'website_id'  => $originalHostname->website_id,
        ]);

        return $hostname;
    }

    /**
     * Limpiar dominio: lowercase, sin protocolo, sin trailing slash.
     */
    protected function cleanDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = rtrim($domain, '/');
        $domain = preg_replace('/:\d+$/', '', $domain);
        return $domain;
    }
}
