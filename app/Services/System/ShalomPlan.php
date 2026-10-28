<?php

namespace App\Services\System;

use App\Models\System\Configuration;
use App\Models\Tenant\ShippingSetting;

/**
 * Qué permite el plan contratado en shalom-api.lat.
 *
 * Es la ÚNICA regla que decide si se muestran las funciones que necesitan
 * instancia (crear guía por API, rótulo oficial de Shalom) o webhooks. Hoy el
 * plan es Básico: todo eso queda apagado y el flujo es manual (el operador
 * sube guía + código del ticket). Al pasar a Business se cambia el plan en
 * /admin/shalom, cada tienda conecta su cuenta Shalom Pro y la interfaz se
 * enciende sola consultando canCreateGuides().
 *
 * Flujo futuro con instancia (ver skill shalom-api):
 *   POST /instances → POST /instances/login (cuenta Shalom Pro de la tienda)
 *   → POST /account/register (origen = shalom_origin_id, destino =
 *     agency_office_id, documento/nombres del cliente; GET /account/dni separa
 *     nombres y apellidos) → guía + código al envío
 *   → GET /track/label (PDF del rótulo oficial) → POST /tracking/subscriptions.
 */
class ShalomPlan
{
    public const PLANS = [
        'basico'   => ['label' => 'Básico (S/29)',   'instances' => 0,    'webhooks' => false],
        'pro'      => ['label' => 'Pro (S/59)',      'instances' => 1,    'webhooks' => true],
        'business' => ['label' => 'Business (S/69)', 'instances' => null, 'webhooks' => true], // null = ilimitadas
    ];

    public static function current(): string
    {
        try {
            $p = Configuration::first()->shalom_plan ?? null;
        } catch (\Throwable $e) {
            $p = null;
        }

        return array_key_exists((string) $p, self::PLANS) ? $p : 'basico';
    }

    public static function info(?string $plan = null): array
    {
        return self::PLANS[$plan ?? self::current()];
    }

    public static function allowsInstances(): bool
    {
        return self::info()['instances'] !== 0;
    }

    public static function allowsWebhooks(): bool
    {
        return self::info()['webhooks'];
    }

    /** ¿Esta tienda puede crear guías y rótulos oficiales por API? */
    public static function canCreateGuides(?ShippingSetting $st = null): bool
    {
        if (!self::allowsInstances()) {
            return false;
        }
        try {
            $st = $st ?? ShippingSetting::current();
        } catch (\Throwable $e) {
            return false;
        }

        return !empty($st->shalom_instance_id) && !empty($st->shalom_origin_id);
    }
}
