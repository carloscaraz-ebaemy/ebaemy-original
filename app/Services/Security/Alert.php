<?php

namespace App\Services\Security;

use Carbon\CarbonImmutable;

/**
 * Una alerta del agente. Es inmutable y se serializa tal cual al JSONL, al
 * reporte HTML y a las notificaciones.
 *
 * `dedupeKey` es lo que evita repetir la misma alerta dentro de la ventana
 * configurada: debe identificar el HECHO, no el momento. Por ejemplo
 * "web_attacks:sql_injection:203.0.113.7" y no incluir la hora.
 */
final class Alert implements \JsonSerializable
{
    public readonly CarbonImmutable $detectedAt;

    public function __construct(
        public readonly string  $module,
        public readonly string  $type,
        public readonly string  $severity,
        public readonly string  $title,
        public readonly string  $recommendation,
        public readonly array   $evidence = [],
        public readonly ?string $tenant = null,
        public readonly ?string $dedupeKey = null,
        ?CarbonImmutable        $detectedAt = null,
    ) {
        $this->detectedAt = $detectedAt ?? CarbonImmutable::now(config('security-agent.timezone', 'America/Lima'));
    }

    public function dedupeKey(): string
    {
        return $this->dedupeKey ?? sprintf('%s:%s:%s', $this->tenant ?? 'system', $this->module, $this->type);
    }

    public function jsonSerialize(): array
    {
        return [
            'detected_at'    => $this->detectedAt->toIso8601String(),
            'tenant'         => $this->tenant,
            'module'         => $this->module,
            'type'           => $this->type,
            'severity'       => $this->severity,
            'title'          => $this->title,
            'evidence'       => $this->evidence,
            'recommendation' => $this->recommendation,
            'dedupe_key'     => $this->dedupeKey(),
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }

    /** Linea compacta para consola y Telegram. */
    public function summary(): string
    {
        $scope = $this->tenant ? "[{$this->tenant}] " : '[sistema] ';

        return sprintf('%s %s %s%s', Severity::emoji($this->severity), $this->severity, $scope, $this->title);
    }
}
