<?php

namespace App\Services;

use App\Models\System\Client;
use App\Models\System\Feature;
use App\Models\System\Plan;
use Hyn\Tenancy\Environment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * FeatureGate — control de acceso a features por plan de suscripción.
 *
 * Resuelve si el tenant activo (identificado por hostname en hyn/tenancy)
 * tiene un feature incluido en su plan.
 *
 * Uso básico:
 *   $gate = app(FeatureGate::class);
 *   if ($gate->has('smart_stock')) { ... }
 *   if ($gate->has('ecommerce'))   { ... }
 *
 * Uso con límite (features metered):
 *   $gate->limit('logistic_module');  // null = ilimitado, int = máximo
 *
 * Integración con Laravel Gate (registrado en AppServiceProvider):
 *   Gate::allows('feature:smart_stock')
 *   @can('feature:ecommerce') ... @endcan
 *
 * Caché:
 *   Los features del plan se cachean por hostname en Redis/Cache por 10 minutos.
 *   Para invalidar en desarrollo: php artisan cache:clear
 */
class FeatureGate
{
    /** @var Collection<string, array>|null Caché en memoria por request */
    private ?Collection $featureCache = null;

    /** @var Plan|null Plan del tenant activo */
    private ?Plan $plan = null;

    /** @var bool Si ya intentamos resolver el plan (evita queries repetidas) */
    private bool $resolved = false;

    public function __construct(private readonly Environment $tenancy) {}

    // ──────────────────────────────────────────────────────────────────────────
    // API pública
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Verifica si el tenant activo tiene acceso al feature indicado.
     *
     * @param  string  $key  Clave del feature (ej: 'smart_stock', 'ecommerce')
     */
    public function has(string $key): bool
    {
        $features = $this->resolveFeatures();

        if ($features === null) {
            // No se pudo resolver el plan (CLI, error de DB, etc.)
            // Fail-closed: denegar acceso por seguridad cuando no se puede verificar.
            Log::warning('[FeatureGate] fail-closed: no se pudo resolver features, denegando acceso.', [
                'feature' => $key,
            ]);
            return false;
        }

        return $features->has($key);
    }

    /**
     * Devuelve el límite configurado para un feature metered.
     * null = ilimitado. 0 = no incluido.
     */
    public function limit(string $key): ?int
    {
        $features = $this->resolveFeatures();

        if ($features === null || !$features->has($key)) {
            return 0; // no incluido
        }

        return $features->get($key)['limit'] ?? null;
    }

    /**
     * Devuelve todos los feature keys del plan activo.
     */
    public function all(): array
    {
        return $this->resolveFeatures()?->keys()->all() ?? [];
    }

    /**
     * Invalida la caché del tenant activo (útil tras cambio de plan).
     */
    public function flush(): void
    {
        $this->featureCache = null;
        $this->plan         = null;
        $this->resolved     = false;

        $cacheKey = $this->cacheKey();
        if ($cacheKey) {
            Cache::forget($cacheKey);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Resolución del plan
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Devuelve la colección de features del plan activo indexada por key.
     * null = no se pudo resolver (CLI sin tenant, error de BD, etc.)
     *
     * @return Collection<string, array>|null
     */
    private function resolveFeatures(): ?Collection
    {
        if ($this->featureCache !== null) {
            return $this->featureCache;
        }

        if ($this->resolved) {
            return null; // ya intentamos y fallamos
        }

        $this->resolved = true;

        $cacheKey = $this->cacheKey();
        if (!$cacheKey) {
            return null; // sin hostname activo
        }

        $this->featureCache = Cache::remember($cacheKey, 600, function () {
            return $this->loadFeaturesFromDb();
        });

        return $this->featureCache;
    }

    private function loadFeaturesFromDb(): ?Collection
    {
        try {
            $hostname = $this->tenancy->hostname();
            if (!$hostname) {
                return null;
            }

            $client = Client::where('hostname_id', $hostname->id)
                ->with(['plan.features'])
                ->first();

            if (!$client || !$client->plan) {
                return null;
            }

            $this->plan = $client->plan;

            // Indexar por feature key → ['limit' => ..., 'meta' => ...]
            return $client->plan->features
                ->where('is_active', true)
                ->keyBy('key')
                ->map(fn($f) => [
                    'limit' => $f->pivot->limit ?? null,
                    'meta'  => $f->pivot->meta  ?? null,
                ]);

        } catch (\Throwable $e) {
            Log::warning('[FeatureGate] No se pudo resolver features del plan.', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function cacheKey(): ?string
    {
        try {
            $hostname = $this->tenancy->hostname();
            return $hostname ? "feature_gate_{$hostname->id}" : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
