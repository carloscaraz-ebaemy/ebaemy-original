<?php

namespace App\Providers;

use App\Models\Tenant\Document;
use App\Observers\DocumentObserver;
use App\Services\FeatureGate;
use App\Services\Tenant\ItemVariantService;
use App\Services\Tenant\ReplicaConnectionManager;
use App\Services\Tenant\TenantContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Modules\LevelAccess\Helpers\SessionLifetimeHelper;


class AppServiceProvider extends ServiceProvider
{
	public function boot()
	{
		// Evitar ejecutar en consola; aplicar sólo en contexto web
		if (!app()->runningInConsole()) {
			SessionLifetimeHelper::setTenantSessionLifetime();
		}

		if (config('tenant.force_https')) {
			URL::forceScheme('https');
		}
		Document::observe(DocumentObserver::class);
		\App\Models\Tenant\Item::observe(\App\Observers\ItemPriceObserver::class);

		// Macro DB::replica() — devuelve la conexión de solo-lectura (réplica)
		// si TENANT_REPLICA_HOST está configurado, o la primaria como fallback.
		// Uso: DB::replica()->table('documents')->where(...)->get();
		DB::macro('replica', function () {
			return app(ReplicaConnectionManager::class)->connection();
		});

		// L4 — Feature Gate: integración con Laravel Gate
		// Permite usar Gate::allows('feature:ecommerce') en código y
		// @can('feature:ecommerce') en Blade.
		// El prefijo 'feature:' distingue estos checks de los gates de autorización normales.
		Gate::before(function ($user, string $ability) {
			if (str_starts_with($ability, 'feature:')) {
				$featureKey = substr($ability, 8); // quitar 'feature:'
				return app(FeatureGate::class)->has($featureKey) ?: null;
				// null = deferir a otros gates si no se reconoce el feature
			}
			return null; // no interceptar gates normales
		});
	}

	public function register()
	{
		// Singleton: una sola query por request para obtener el rubro del tenant.
		// Uso: app(TenantContextService::class)->isRestaurant()
		$this->app->singleton(TenantContextService::class);
		$this->app->singleton(ItemVariantService::class);
		$this->app->singleton(ReplicaConnectionManager::class);
		$this->app->singleton(FeatureGate::class);
	}
}
