<?php

namespace App\Providers;

use Hyn\Tenancy\Environment;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Cache::extend('redis_tenancy', function ($app) {
            $uuid = $this->resolveTenantUuid($app);

            return Cache::repository(new RedisStore(
                $app['redis'],
                $uuid,
                $app['config']['cache.stores.redis.connection']
            ));
        });
    }

    /**
     * Resolve the current tenant UUID for cache prefix isolation.
     *
     * Priority:
     *  1. Hyn Environment (works in web + CLI when tenant is active)
     *  2. SERVER_NAME hostname lookup (web fallback)
     *  3. 'system' prefix (console with no active tenant)
     */
    private function resolveTenantUuid($app): string
    {
        // 1. Ask Hyn's tenancy environment (available after middleware or manual switch)
        try {
            $website = $app->make(Environment::class)->tenant();
            if ($website && $website->uuid) {
                return $website->uuid;
            }
        } catch (\Throwable) {
            // Environment not booted yet — continue to fallbacks
        }

        // 2. Web fallback: resolve via SERVER_NAME → hostnames → websites
        $fqdn = $_SERVER['SERVER_NAME'] ?? null;
        if ($fqdn) {
            $uuid = DB::table('hostnames')
                ->select('websites.uuid')
                ->join('websites', 'hostnames.website_id', '=', 'websites.id')
                ->where('hostnames.fqdn', $fqdn)
                ->value('uuid');

            if ($uuid) {
                return $uuid;
            }
        }

        // 3. No tenant context (system-level CLI with no active tenant)
        return 'system';
    }
}