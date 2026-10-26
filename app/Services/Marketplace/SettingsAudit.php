<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de la configuración de publicidad del marketplace: quién cambió
 * qué y cuándo. Los secretos (tokens) se guardan ENMASCARADOS: lo que queda es
 * que existe, su largo y sus 4 últimos caracteres, suficiente para saber cuál
 * está puesto sin poder usarlo.
 */
class SettingsAudit
{
    /** Campo → [etiqueta para el panel, ¿es secreto?] */
    public const FIELDS = [
        'marketplace_ads_enabled'              => ['Medición activa', false],
        'marketplace_meta_pixel_id'            => ['Píxel de Meta', false],
        'marketplace_meta_capi_token'          => ['Token API de conversiones (Meta)', true],
        'marketplace_tiktok_pixel_id'          => ['Píxel de TikTok', false],
        'marketplace_tiktok_capi_token'        => ['Token Events API (TikTok)', true],
        'marketplace_ga4_id'                   => ['Google Analytics 4', false],
        'marketplace_ads_test_code'            => ['Código de prueba de Meta', false],
        'marketplace_fb_domain_verification'   => ['Verificación de dominio (Meta)', false],
        'marketplace_google_site_verification' => ['Verificación de dominio (Google)', false],
        'marketplace_tiktok_site_verification' => ['Verificación de dominio (TikTok)', false],
    ];

    public static function available(): bool
    {
        try {
            return Schema::connection('system')->hasTable('marketplace_settings_log');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * «•••• CaPQZDZD · 207 caracteres». Ocho caracteres y no cuatro: los
     * tokens de Meta suelen terminar todos en «ZDZD», así que con cuatro el
     * historial no distinguía un token de su reemplazo.
     */
    public static function mask(?string $v): ?string
    {
        $v = (string) $v;
        if ($v === '') {
            return null;
        }

        return '•••• ' . substr($v, -8) . ' · ' . strlen($v) . ' caracteres';
    }

    public static function display(string $field, $value): ?string
    {
        if ($field === 'marketplace_ads_enabled') {
            return $value ? 'Encendida' : 'Apagada';
        }
        $secreto = self::FIELDS[$field][1] ?? false;

        return $secreto ? self::mask($value) : (($value === null || $value === '') ? null : (string) $value);
    }

    /**
     * Registra los campos que cambiaron entre $antes y $despues.
     *
     * @param array<string,mixed> $antes
     * @param array<string,mixed> $despues
     */
    public static function recordChanges(array $antes, array $despues, ?string $note = null, ?object $user = null): int
    {
        if (!self::available()) {
            return 0;
        }
        $user = $user ?? auth()->user();
        $n = 0;
        foreach (self::FIELDS as $field => $_) {
            if (!array_key_exists($field, $despues)) {
                continue;
            }
            $old = $antes[$field] ?? null;
            $new = $despues[$field] ?? null;
            if ($field === 'marketplace_ads_enabled') {
                $old = (bool) $old;
                $new = (bool) $new;
            }
            if ((string) $old === (string) $new) {
                continue;
            }
            $vacio = $new === null || $new === '' || $new === false;
            self::record($field, $vacio ? 'cleared' : (($old === null || $old === '' || $old === false) ? 'set' : 'changed'),
                self::display($field, $new), $note, $user);
            $n++;
        }

        return $n;
    }

    public static function record(string $field, string $action, ?string $value, ?string $note = null, ?object $user = null): void
    {
        try {
            DB::connection('system')->table('marketplace_settings_log')->insert([
                'user_id'    => $user->id ?? null,
                'user_name'  => mb_substr((string) ($user->name ?? $user->email ?? 'sistema'), 0, 120),
                'field'      => $field,
                'action'     => $action,
                'value'      => $value !== null ? mb_substr($value, 0, 160) : null,
                'note'       => $note !== null ? mb_substr($note, 0, 200) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[SettingsAudit] no se pudo registrar el cambio', ['field' => $field, 'error' => $e->getMessage()]);
        }
    }

    /** Últimos cambios para el panel. */
    public static function latest(int $limit = 20): array
    {
        if (!self::available()) {
            return [];
        }

        return DB::connection('system')->table('marketplace_settings_log')
            ->orderByDesc('id')->limit($limit)->get()
            ->map(fn ($r) => [
                'when'   => $r->created_at,
                'who'    => $r->user_name,
                'label'  => self::FIELDS[$r->field][0] ?? $r->field,
                'action' => ['set' => 'Configurado', 'changed' => 'Cambiado', 'cleared' => 'Borrado'][$r->action] ?? $r->action,
                'value'  => $r->value,
                'note'   => $r->note,
            ])->all();
    }
}
