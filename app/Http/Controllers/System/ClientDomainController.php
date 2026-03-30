<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateSslCertificate;
use App\Jobs\VerifyDomainDns;
use App\Models\System\Client;
use App\Models\System\DomainVerification;
use App\Services\System\DomainVerificationService;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Http\Request;

/**
 * ClientDomainController — Gestión de dominios de un client (Super Admin).
 */
class ClientDomainController extends Controller
{
    /**
     * Listar todos los dominios (hostnames) de un client.
     */
    public function index($clientId)
    {
        $client = Client::findOrFail($clientId);
        $websiteId = $client->hostname->website_id ?? null;

        if (!$websiteId) {
            return response()->json(['data' => [], 'verifications' => []]);
        }

        $hostnames = Hostname::where('website_id', $websiteId)
            ->get()
            ->map(function ($h) {
                return [
                    'id'                   => $h->id,
                    'fqdn'                 => $h->fqdn,
                    'domain_type'          => $h->domain_type ?? 'subdomain',
                    'is_primary'           => (bool) ($h->is_primary ?? false),
                    'redirect_to_primary'  => (bool) ($h->redirect_to_primary ?? false),
                    'ssl_status'           => $h->ssl_status ?? 'none',
                    'ssl_expires_at'       => $h->ssl_expires_at ?? null,
                    'force_https'          => (bool) $h->force_https,
                    'created_at'           => $h->created_at?->toDateTimeString(),
                ];
            });

        $verifications = DomainVerification::where('hostname_id', $client->hostname_id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($v) {
                return array_merge($v->toArray(), [
                    'instructions' => $v->getVerificationInstructions(),
                ]);
            });

        return response()->json([
            'data'          => $hostnames,
            'verifications' => $verifications,
        ]);
    }

    /**
     * Agregar un dominio personalizado + crear verificación.
     */
    public function store(Request $request, $clientId)
    {
        $request->validate([
            'domain' => 'required|string|max:255',
            'method' => 'required|in:dns_cname,dns_txt',
        ]);

        $client = Client::findOrFail($clientId);
        $service = new DomainVerificationService();

        try {
            $verification = $service->createVerification(
                $client,
                $request->input('domain'),
                $request->input('method')
            );

            return response()->json([
                'success'      => true,
                'message'      => 'Verificación creada. Configure su DNS y haga clic en "Verificar".',
                'verification' => array_merge($verification->toArray(), [
                    'instructions' => $verification->getVerificationInstructions(),
                ]),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Verificar un dominio manualmente.
     */
    public function verify($verificationId)
    {
        $verification = DomainVerification::findOrFail($verificationId);
        $service = new DomainVerificationService();

        $result = $service->verify($verification);

        if ($result) {
            // Activar el dominio (crear hostname)
            $hostname = $service->activateDomain($verification);

            // Despachar generación de SSL
            if ($hostname) {
                GenerateSslCertificate::dispatch($hostname->id)->delay(now()->addSeconds(10));
            }

            return response()->json([
                'success' => true,
                'message' => 'Dominio verificado y activado exitosamente.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Verificación falló. Error: ' . ($verification->last_error ?? 'Registro DNS no encontrado'),
        ], 422);
    }

    /**
     * Establecer un hostname como principal.
     */
    public function setPrimary($hostnameId)
    {
        $hostname = Hostname::findOrFail($hostnameId);

        // Quitar primary de los demás del mismo website
        Hostname::where('website_id', $hostname->website_id)
            ->where('id', '!=', $hostname->id)
            ->update(['is_primary' => false]);

        $hostname->is_primary = true;
        $hostname->save();

        return response()->json([
            'success' => true,
            'message' => "'{$hostname->fqdn}' es ahora el dominio principal.",
        ]);
    }

    /**
     * Toggle redirección al dominio principal.
     */
    public function toggleRedirect($hostnameId)
    {
        $hostname = Hostname::findOrFail($hostnameId);
        $hostname->redirect_to_primary = !($hostname->redirect_to_primary ?? false);
        $hostname->save();

        return response()->json([
            'success'  => true,
            'message'  => $hostname->redirect_to_primary ? 'Redirección activada' : 'Redirección desactivada',
            'redirect' => (bool) $hostname->redirect_to_primary,
        ]);
    }

    /**
     * Eliminar un hostname (solo custom domains).
     */
    public function destroy($hostnameId)
    {
        $hostname = Hostname::findOrFail($hostnameId);

        // No permitir eliminar el hostname principal del subdominio
        if (($hostname->domain_type ?? 'subdomain') === 'subdomain') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el subdominio principal.',
            ], 422);
        }

        $hostname->delete();

        return response()->json([
            'success' => true,
            'message' => "Dominio '{$hostname->fqdn}' eliminado.",
        ]);
    }

    /**
     * Obtener instrucciones de verificación DNS.
     */
    public function getVerificationInstructions($verificationId)
    {
        $verification = DomainVerification::findOrFail($verificationId);
        return response()->json($verification->getVerificationInstructions());
    }

    /**
     * Cambiar el subdominio (FQDN) de un hostname.
     */
    public function changeSubdomain(Request $request, $hostnameId)
    {
        $hostname = Hostname::findOrFail($hostnameId);
        $subdomain = strtolower(trim($request->input('subdomain', '')));

        // Validar formato
        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $subdomain)) {
            return response()->json([
                'success' => false,
                'message' => 'Subdominio inválido. Solo letras minúsculas, números y guiones.',
            ], 422);
        }

        // Construir nuevo FQDN
        $baseDomain = config('tenant.base_domain', 'ebaemy.test');
        $newFqdn = $subdomain . '.' . $baseDomain;

        // Verificar que no exista
        $exists = Hostname::where('fqdn', $newFqdn)->where('id', '!=', $hostname->id)->exists();
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => "El subdominio '{$newFqdn}' ya está en uso.",
            ], 422);
        }

        $oldFqdn = $hostname->fqdn;
        $hostname->fqdn = $newFqdn;
        $hostname->save();

        // Limpiar caché del dominio anterior
        app(\App\Services\TenantManager::class)->flushDomainCache($oldFqdn);

        return response()->json([
            'success' => true,
            'message' => "Subdominio cambiado: {$oldFqdn} → {$newFqdn}",
        ]);
    }
}
