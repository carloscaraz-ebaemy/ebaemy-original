<?php

namespace App\Services\System;

use App\Models\System\Configuration;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente de shalom-api.lat (intermediario NO oficial de Shalom).
 *
 * Una sola llave de ebaemy para todas las tiendas. Manda la que guardó el
 * SuperAdmin en /admin/shalom (cifrada en `configurations`); SHALOM_API_KEY
 * del .env solo cuenta mientras nadie haya guardado una.
 *
 * Nada del formulario del cliente llama aquí: el catálogo se copia a
 * `shalom_agencies` y se lee de ahí (ver ShalomAgencyCatalog), así una llave
 * vencida no deja al cliente sin oficinas.
 */
class ShalomApi
{
    public function key(): ?string
    {
        try {
            $key = Configuration::first()->shalom_api_key ?? null;
        } catch (\Throwable $e) {
            // Columna aún sin migrar, o APP_KEY cambiada y el cifrado ya no abre.
            $key = null;
        }

        return $key ?: (config('services.shalom.key') ?: null);
    }

    /** De dónde sale la llave en uso: 'panel', 'env' o null. */
    public function keySource(): ?string
    {
        try {
            if (Configuration::first()->shalom_api_key ?? null) {
                return 'panel';
            }
        } catch (\Throwable $e) {
        }

        return config('services.shalom.key') ? 'env' : null;
    }

    public function isConfigured(): bool
    {
        return (bool) $this->key();
    }

    /**
     * GET /validate — no consume cuota.
     * Devuelve { valid, limit, currentUsage, remaining, message }.
     */
    public function validate(?string $key = null): array
    {
        return $this->get('/validate', [], $key, 20);
    }

    /** GET /agencies — el catálogo completo (~560 oficinas) en una llamada. */
    public function agencies(): array
    {
        $body = $this->get('/agencies', [], null, 60);

        if (!isset($body['data']) || !is_array($body['data'])) {
            throw new RuntimeException('Shalom API respondió sin lista de agencias.');
        }

        return $body['data'];
    }

    private function get(string $path, array $query, ?string $key, int $timeout): array
    {
        $key = $key ?: $this->key();
        if (!$key) {
            throw new RuntimeException('No hay llave de Shalom API configurada.');
        }

        $res = Http::withHeaders(['x-api-key' => $key, 'Accept' => 'application/json'])
            ->timeout($timeout)
            // Con un config:cache anterior al deploy, `services.shalom` no existe.
            ->get(rtrim(config('services.shalom.url') ?: 'https://api.shalom-api.lat', '/') . $path, $query);

        if ($res->failed()) {
            $msg = $res->json('error') ?? $res->json('message') ?? substr($res->body(), 0, 200);
            throw new RuntimeException('Shalom API ' . $res->status() . ': ' . $msg, $res->status());
        }

        return $res->json() ?? [];
    }
}
