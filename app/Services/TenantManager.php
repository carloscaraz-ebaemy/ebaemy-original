<?php

namespace App\Services;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * TenantManager — Gestión centralizada del tenant en contexto.
 *
 * Wrapper profesional sobre hyn/multi-tenant que agrega:
 *  - Caché de resolución por dominio (Redis, TTL 1h)
 *  - Gestión de estados (active, suspended, trial_expired, pending)
 *  - Configuración dinámica (timezone, locale, currency)
 *  - Ejecución en contexto de tenant específico
 *  - Invalidación de caché selectiva
 *
 * Uso:
 *   app(TenantManager::class)->current()        // Tenant actual
 *   app(TenantManager::class)->id()              // UUID del tenant
 *   tenant()                                      // Helper global
 *   tenant('name')                                // Atributo del company
 */
class TenantManager
{
    protected ?Hostname $hostname = null;
    protected ?Website $website = null;
    protected ?object $company = null;
    protected ?object $config = null;
    protected bool $resolved = false;

    /** TTL de caché de resolución de dominio en segundos */
    private const CACHE_TTL = 3600;

    /** Prefijo de caché */
    private const CACHE_PREFIX = 'tenant:resolve:';

    /** Estados válidos del tenant */
    public const STATE_ACTIVE        = 'active';
    public const STATE_SUSPENDED     = 'suspended';
    public const STATE_PENDING       = 'pending';
    public const STATE_TRIAL_EXPIRED = 'trial_expired';

    /**
     * Resolver tenant desde un FQDN (hostname).
     * Usa caché Redis para evitar queries repetidas.
     *
     * @return bool True si se resolvió exitosamente
     */
    public function resolveFromHost(string $fqdn): bool
    {
        $fqdn = $this->cleanHost($fqdn);

        // Intentar desde caché
        $cached = Cache::get(self::CACHE_PREFIX . $fqdn);
        if ($cached !== null) {
            if ($cached === false) {
                // Cacheamos que NO existe para evitar queries en cada request
                return false;
            }
            return $this->activateFromCache($cached);
        }

        // Resolver desde BD
        $hostname = Hostname::where('fqdn', $fqdn)->with('website')->first();

        if (!$hostname || !$hostname->website) {
            // Cachear resultado negativo (TTL corto: 5 min)
            Cache::put(self::CACHE_PREFIX . $fqdn, false, 300);
            return false;
        }

        // Cachear resultado positivo
        Cache::put(self::CACHE_PREFIX . $fqdn, [
            'hostname_id' => $hostname->id,
            'website_id'  => $hostname->website_id,
            'uuid'        => $hostname->website->uuid,
        ], self::CACHE_TTL);

        return $this->activate($hostname);
    }

    /**
     * Activar un tenant desde datos cacheados.
     */
    protected function activateFromCache(array $data): bool
    {
        try {
            $hostname = Hostname::with('website')->find($data['hostname_id']);
            if (!$hostname || !$hostname->website) {
                return false;
            }
            return $this->activate($hostname);
        } catch (\Throwable $e) {
            Log::warning('TenantManager: cache activation failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Activar tenant via hyn/multi-tenant.
     */
    protected function activate(Hostname $hostname): bool
    {
        try {
            $environment = app(Environment::class);
            $environment->tenant($hostname->website);

            $this->hostname = $hostname;
            $this->website  = $hostname->website;
            $this->resolved = true;

            return true;
        } catch (\Throwable $e) {
            Log::error('TenantManager: activation failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Obtener el hostname actual.
     */
    public function hostname(): ?Hostname
    {
        return $this->hostname;
    }

    /**
     * Obtener el website (tenant) actual.
     */
    public function website(): ?Website
    {
        if (!$this->website) {
            try {
                $env = app(Environment::class);
                $this->website = $env->tenant();
            } catch (\Throwable $e) {
                // Sin tenant
            }
        }
        return $this->website;
    }

    /**
     * UUID del tenant actual.
     */
    public function id(): ?string
    {
        return $this->website()?->uuid;
    }

    /**
     * ¿Hay un tenant resuelto?
     */
    public function check(): bool
    {
        return $this->website() !== null;
    }

    /**
     * Company del tenant actual (cacheada en memoria).
     *
     * @param string|null $key Atributo específico del company
     * @return mixed
     */
    public function company(?string $key = null)
    {
        if (!$this->check()) {
            return null;
        }

        if ($this->company === null) {
            try {
                $this->company = \App\Models\Tenant\Company::first();
            } catch (\Throwable $e) {
                return null;
            }
        }

        if ($key !== null) {
            return $this->company?->{$key};
        }

        return $this->company;
    }

    /**
     * Configuration del tenant (cacheada).
     *
     * @param string|null $key Atributo específico
     * @return mixed
     */
    public function config(?string $key = null)
    {
        if (!$this->check()) {
            return null;
        }

        if ($this->config === null) {
            try {
                $this->config = \App\Models\Tenant\Configuration::firstCached();
            } catch (\Throwable $e) {
                return null;
            }
        }

        if ($key !== null) {
            return $this->config?->{$key};
        }

        return $this->config;
    }

    /**
     * Setting del ecommerce del tenant.
     */
    public function ecommerceSetting(string $key, $default = null)
    {
        try {
            $econfig = \App\Models\Tenant\ConfigurationEcommerce::firstCached();
            return $econfig?->{$key} ?? $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Verificar estado del tenant.
     */
    public function state(): string
    {
        $config = $this->config();
        if (!$config) {
            return self::STATE_PENDING;
        }

        if ($config->locked_tenant ?? false) {
            return self::STATE_SUSPENDED;
        }

        return self::STATE_ACTIVE;
    }

    /**
     * ¿Está activo el tenant?
     */
    public function isActive(): bool
    {
        return $this->state() === self::STATE_ACTIVE;
    }

    /**
     * ¿Tiene el tenant una feature habilitada?
     */
    public function hasFeature(string $feature): bool
    {
        try {
            $gate = app(\App\Services\FeatureGate::class);
            return $gate->allows($feature);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Ejecutar closure en contexto de un tenant específico.
     * Útil para jobs, commands, y operaciones cross-tenant.
     *
     * @param string|int $websiteIdOrUuid UUID o ID del website
     * @param \Closure $callback
     * @return mixed
     */
    public function run($websiteIdOrUuid, \Closure $callback)
    {
        $website = is_numeric($websiteIdOrUuid)
            ? Website::find($websiteIdOrUuid)
            : Website::where('uuid', $websiteIdOrUuid)->first();

        if (!$website) {
            throw new \RuntimeException("Tenant not found: {$websiteIdOrUuid}");
        }

        $environment = app(Environment::class);
        $previous    = $environment->tenant();

        try {
            $environment->tenant($website);
            $result = $callback($website);
        } finally {
            // Restaurar tenant anterior
            if ($previous) {
                $environment->tenant($previous);
            }
        }

        return $result;
    }

    /**
     * Invalidar caché de un dominio específico.
     */
    public function flushDomainCache(string $fqdn): void
    {
        Cache::forget(self::CACHE_PREFIX . $this->cleanHost($fqdn));
    }

    /**
     * Invalidar toda la caché del tenant actual.
     */
    public function flushCurrentCache(): void
    {
        if ($this->hostname) {
            $this->flushDomainCache($this->hostname->fqdn);
        }

        // También limpiar caché de configuraciones
        try {
            \App\Models\Tenant\Configuration::flushCache();
            \App\Models\Tenant\ConfigurationEcommerce::flushCache();
        } catch (\Throwable $e) {
            // Silenciar si las tablas no existen
        }
    }

    /**
     * Limpiar hostname: sin www, sin puerto, lowercase.
     */
    protected function cleanHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host); // Quitar puerto
        $host = preg_replace('/^www\./', '', $host); // Quitar www
        return $host;
    }

    /**
     * Extraer el subdominio de un FQDN.
     * Ej: "cliente.miapp.com" → "cliente"
     */
    public function extractSubdomain(string $fqdn): ?string
    {
        $baseDomain = config('tenancy.hostname.default');
        if (!$baseDomain) {
            return null;
        }

        $fqdn = $this->cleanHost($fqdn);
        $suffix = '.' . ltrim($baseDomain, '.');

        if (str_ends_with($fqdn, $suffix)) {
            $sub = substr($fqdn, 0, -strlen($suffix));
            return $sub ?: null;
        }

        return null;
    }
}
