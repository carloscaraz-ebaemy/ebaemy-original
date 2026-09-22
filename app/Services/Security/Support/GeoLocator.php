<?php

namespace App\Services\Security\Support;

use App\Services\Security\State\FileStateStore;
use Carbon\CarbonImmutable;
use Stevebauman\Location\Facades\Location;

/**
 * Geolocalizacion de IPs con cache en disco.
 *
 * Se resuelve aqui, en el escaneo, y no en el momento del login: asi ningun
 * usuario espera una llamada HTTP para entrar al sistema. El cache evita
 * volver a consultar la misma IP durante los dias configurados.
 */
final class GeoLocator
{
    private const NAMESPACE = 'geo_cache';

    public function __construct(
        private readonly FileStateStore $state,
        private readonly int $cacheDays = 30,
        private readonly bool $enabled = true,
    ) {}

    /**
     * @return array{country:?string,city:?string,lat:?float,lon:?float,private:bool}
     */
    public function locate(?string $ip): array
    {
        $empty = ['country' => null, 'city' => null, 'lat' => null, 'lon' => null, 'private' => false];

        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return $empty;
        }

        if ($this->isPrivate($ip)) {
            return array_merge($empty, ['private' => true]);
        }

        if (!$this->enabled) {
            return $empty;
        }

        $cached = $this->state->get(self::NAMESPACE, $ip);
        $now    = CarbonImmutable::now()->getTimestamp();

        if (is_array($cached) && ($cached['at'] ?? 0) > $now - ($this->cacheDays * 86400)) {
            return array_merge($empty, array_diff_key($cached, ['at' => null]));
        }

        $resolved = $empty;

        try {
            $position = Location::get($ip);

            if ($position) {
                $resolved = [
                    'country' => $position->countryCode ? strtoupper($position->countryCode) : null,
                    'city'    => $position->cityName ?: null,
                    'lat'     => $position->latitude !== null ? (float) $position->latitude : null,
                    'lon'     => $position->longitude !== null ? (float) $position->longitude : null,
                    'private' => false,
                ];
            }
        } catch (\Throwable $e) {
            // Sin red o proveedor caido: devolvemos vacio y NO cacheamos el fallo.
            return $empty;
        }

        if ($resolved['country'] !== null) {
            $this->state->put(self::NAMESPACE, $ip, array_merge($resolved, ['at' => $now]));
        }

        return $resolved;
    }

    public function isPrivate(string $ip): bool
    {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /** Distancia en kilometros entre dos puntos (formula del haversine). */
    public static function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371.0;
        $dLat  = deg2rad($lat2 - $lat1);
        $dLon  = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
