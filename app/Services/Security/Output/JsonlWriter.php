<?php

namespace App\Services\Security\Output;

use App\Services\Security\Alert;
use Carbon\CarbonImmutable;

/**
 * Historial de alertas en JSONL: una alerta por linea, apta para `grep`,
 * `jq` o para cargarla en cualquier herramienta de analisis.
 */
final class JsonlWriter
{
    public function __construct(private readonly string $path) {}

    public static function make(): self
    {
        $dir = storage_path('app/' . config('security-agent.storage_path', 'security-agent'));

        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        return new self($dir . DIRECTORY_SEPARATOR . 'alerts.jsonl');
    }

    public function path(): string
    {
        return $this->path;
    }

    /** @param  Alert[]  $alerts */
    public function append(array $alerts): void
    {
        if (!$alerts) return;

        $lines = '';

        foreach ($alerts as $alert) {
            $lines .= json_encode($alert->jsonSerialize(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        }

        file_put_contents($this->path, $lines, FILE_APPEND | LOCK_EX);
    }

    /** Borra las lineas mas antiguas que $days. Devuelve cuantas elimino. */
    public function prune(int $days): int
    {
        if (!is_file($this->path) || $days <= 0) return 0;

        $limit   = CarbonImmutable::now()->subDays($days);
        $kept    = '';
        $removed = 0;

        foreach (file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $row = json_decode($line, true);

            if (is_array($row) && !empty($row['detected_at'])) {
                try {
                    if (CarbonImmutable::parse($row['detected_at'])->lt($limit)) {
                        $removed++;
                        continue;
                    }
                } catch (\Throwable $e) {
                    // Linea ilegible: la conservamos, no la perdemos en silencio.
                }
            }

            $kept .= $line . PHP_EOL;
        }

        if ($removed > 0) {
            file_put_contents($this->path, $kept, LOCK_EX);
        }

        return $removed;
    }

    /** Ultimas N alertas del historial, de la mas reciente a la mas antigua. */
    public function tail(int $limit = 50): array
    {
        if (!is_file($this->path)) return [];

        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $slice = array_slice($lines, -$limit);

        return array_reverse(array_values(array_filter(array_map(
            fn ($line) => json_decode($line, true),
            $slice
        ))));
    }
}
