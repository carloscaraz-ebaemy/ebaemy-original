<?php

namespace App\Services\Security\State;

use Carbon\CarbonImmutable;

/**
 * Estado persistente del agente, en archivos JSON dentro de storage/app.
 *
 * Deliberadamente NO usa la base de datos: la restriccion del agente es no
 * escribir nada en produccion. Aqui viven las IPs ya conocidas de cada usuario,
 * los hashes SHA-256 de los archivos vigilados, el offset de lectura del access
 * log, las metricas de la revision anterior y el registro de deduplicacion.
 */
final class FileStateStore
{
    private array $cache = [];
    private array $dirty = [];

    public function __construct(private readonly string $basePath)
    {
        if (!is_dir($this->basePath)) {
            @mkdir($this->basePath, 0775, true);
        }
    }

    public static function make(): self
    {
        $dir = storage_path('app/' . config('security-agent.storage_path', 'security-agent') . '/state');

        return new self($dir);
    }

    public function get(string $namespace, string $key, $default = null)
    {
        return $this->read($namespace)[$key] ?? $default;
    }

    public function put(string $namespace, string $key, $value): void
    {
        $data = $this->read($namespace);
        $data[$key] = $value;
        $this->cache[$namespace] = $data;
        $this->dirty[$namespace] = true;
    }

    public function forget(string $namespace, string $key): void
    {
        $data = $this->read($namespace);
        unset($data[$key]);
        $this->cache[$namespace] = $data;
        $this->dirty[$namespace] = true;
    }

    public function all(string $namespace): array
    {
        return $this->read($namespace);
    }

    /** Anade un valor a una lista sin duplicados y la recorta a $max entradas. */
    public function push(string $namespace, string $key, $value, int $max = 500): void
    {
        $list = (array) $this->get($namespace, $key, []);

        if (!in_array($value, $list, true)) {
            $list[] = $value;
        }

        if (count($list) > $max) {
            $list = array_slice($list, -$max);
        }

        $this->put($namespace, $key, array_values($list));
    }

    // ── Deduplicacion ─────────────────────────────────────────────────────────

    /**
     * ¿Ya avisamos de esto dentro de la ventana? Si no, lo marca y devuelve
     * false (es decir: "esta alerta si se emite").
     */
    public function isDuplicate(string $dedupeKey, int $windowHours, CarbonImmutable $now): bool
    {
        $seen  = $this->read('dedupe');
        $limit = $now->subHours($windowHours)->getTimestamp();

        if (isset($seen[$dedupeKey]) && $seen[$dedupeKey] >= $limit) {
            return true;
        }

        $seen[$dedupeKey] = $now->getTimestamp();

        // Poda: no dejamos crecer el archivo indefinidamente.
        foreach ($seen as $key => $timestamp) {
            if ($timestamp < $limit) unset($seen[$key]);
        }

        $this->cache['dedupe'] = $seen;
        $this->dirty['dedupe'] = true;

        return false;
    }

    // ── Persistencia ──────────────────────────────────────────────────────────

    public function flush(): void
    {
        foreach (array_keys($this->dirty) as $namespace) {
            $this->write($namespace, $this->cache[$namespace] ?? []);
        }

        $this->dirty = [];
    }

    private function read(string $namespace): array
    {
        if (isset($this->cache[$namespace])) {
            return $this->cache[$namespace];
        }

        $path = $this->pathFor($namespace);
        $data = [];

        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) $data = $decoded;
        }

        return $this->cache[$namespace] = $data;
    }

    private function write(string $namespace, array $data): void
    {
        $path = $this->pathFor($namespace);
        $tmp  = $path . '.tmp';

        file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        @rename($tmp, $path);
    }

    private function pathFor(string $namespace): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $namespace);

        return rtrim($this->basePath, '/\\') . DIRECTORY_SEPARATOR . $safe . '.json';
    }
}
