<?php

namespace App\Services\Security;

use App\Services\Security\State\FileStateStore;
use App\Services\Security\Support\AgentConfig;
use Carbon\CarbonImmutable;

/**
 * Lo que recibe cada detector: el momento de la corrida, el tenant en curso
 * (null si el modulo es de sistema), la configuracion ya fusionada y el estado
 * persistente en disco.
 */
final class ScanContext
{
    public function __construct(
        public readonly CarbonImmutable $now,
        public readonly AgentConfig     $config,
        public readonly FileStateStore  $state,
        public readonly ?string         $tenant = null,
        public readonly ?string         $tenantHostname = null,
    ) {}

    /** Etiqueta para el estado: separa el historial de cada tenant. */
    public function scopeKey(): string
    {
        return $this->tenant ?? 'system';
    }

    public function withTenant(?string $tenant, ?string $hostname = null): self
    {
        return new self($this->now, $this->config, $this->state, $tenant, $hostname);
    }
}
