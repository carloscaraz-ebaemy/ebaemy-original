<?php

namespace App\Services\Security;

/**
 * Escala de severidad del agente. El orden importa: se usa para filtrar que
 * se notifica y para ordenar el reporte.
 */
final class Severity
{
    public const BAJA    = 'BAJA';
    public const MEDIA   = 'MEDIA';
    public const ALTA    = 'ALTA';
    public const CRITICA = 'CRITICA';

    private const RANK = [
        self::BAJA    => 1,
        self::MEDIA   => 2,
        self::ALTA    => 3,
        self::CRITICA => 4,
    ];

    public static function rank(string $severity): int
    {
        return self::RANK[strtoupper($severity)] ?? 0;
    }

    public static function atLeast(string $severity, string $minimum): bool
    {
        return self::rank($severity) >= self::rank($minimum);
    }

    public static function all(): array
    {
        return array_keys(self::RANK);
    }

    /** Traduce un puntaje 0-100 de fraude a severidad, con umbrales configurables. */
    public static function fromScore(int $score, array $thresholds): ?string
    {
        if ($score >= ($thresholds['critica'] ?? 85)) return self::CRITICA;
        if ($score >= ($thresholds['alta'] ?? 60))    return self::ALTA;
        if ($score >= ($thresholds['media'] ?? 35))   return self::MEDIA;

        return null;
    }

    public static function emoji(string $severity): string
    {
        return match (strtoupper($severity)) {
            self::CRITICA => '🔴',
            self::ALTA    => '🟠',
            self::MEDIA   => '🟡',
            default       => '🔵',
        };
    }
}
