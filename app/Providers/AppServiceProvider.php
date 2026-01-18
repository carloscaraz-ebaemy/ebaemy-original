<?php

namespace App\Providers;

use App\Models\Tenant\Document;
use App\Observers\DocumentObserver;
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
	}

	public function register()
	{
	}
}
