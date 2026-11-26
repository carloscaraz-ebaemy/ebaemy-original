<?php

namespace App\Services\Marketplace;

/**
 * Frases para «cómo le pagas a la tienda» a partir del bloque `pay` del
 * resumen de la tienda (StoreShipping::summary()['pay']), que viaja copiado
 * en cada listing (`marketplace_listings.shipping`), en el carrito y en el
 * pedido.
 *
 * Estructura de `pay` (todas las claves opcionales):
 *   yape:     ['phone' => '9xxxxxxxx', 'name' => 'Titular']
 *   plin:     ['phone' => ..., 'name' => ...]
 *   transfer: 'BCP soles 191-...-0-12 · CCI 002-...'   (texto libre)
 *   cash:     true   (efectivo al recibir / contra entrega)
 *   card:     true   (POS al recibir)
 *   notes:    'Envíanos la captura por WhatsApp'
 *
 * En la ficha y el checkout solo se nombran los medios («Yape, Plin o
 * transferencia»); los datos completos (número, cuenta) aparecen en la
 * confirmación del pedido, cuando ya hay algo que pagar.
 */
class PaymentSummaryText
{
    public const PENDING = 'La tienda te indica cómo pagar';

    /** El bloque `pay` de un resumen de tienda, o null. */
    public static function from(?array $shippingSummary): ?array
    {
        $p = $shippingSummary['pay'] ?? null;

        return is_array($p) && self::methods($p) ? $p : null;
    }

    /** Nombres de los medios aceptados, en el orden en que se ofrecen. */
    public static function methods(?array $p): array
    {
        if (!$p) {
            return [];
        }
        $out = [];
        if (!empty($p['yape']['phone'])) {
            $out[] = 'Yape';
        }
        if (!empty($p['plin']['phone'])) {
            $out[] = 'Plin';
        }
        if (!empty($p['transfer'])) {
            $out[] = 'Transferencia';
        }
        if (!empty($p['cash'])) {
            $out[] = 'Efectivo al recibir';
        }
        if (!empty($p['card'])) {
            $out[] = 'Tarjeta al recibir';
        }

        return $out;
    }

    /** «Yape, Plin o transferencia» para la ficha y el checkout; null si no hay nada. */
    public static function short(?array $p): ?string
    {
        $m = self::methods($p);
        if (!$m) {
            return null;
        }
        $m = array_map(fn ($x) => $x === 'Efectivo al recibir' ? 'efectivo al recibir' : ($x === 'Tarjeta al recibir' ? 'tarjeta al recibir' : ($x === 'Transferencia' ? 'transferencia' : $x)), $m);
        if (count($m) === 1) {
            return $m[0];
        }
        $last = array_pop($m);

        return implode(', ', $m) . ' o ' . $last;
    }

    /**
     * Instrucciones completas para la confirmación del pedido.
     *
     * @return array<int, array{method:string, detail:string}>
     */
    public static function instructions(?array $p): array
    {
        if (!$p) {
            return [];
        }
        $out = [];
        foreach (['yape' => 'Yape', 'plin' => 'Plin'] as $k => $label) {
            if (!empty($p[$k]['phone'])) {
                $detail = self::phone((string) $p[$k]['phone']);
                if (!empty($p[$k]['name'])) {
                    $detail .= ' · ' . $p[$k]['name'];
                }
                $out[] = ['method' => $label, 'detail' => $detail];
            }
        }
        if (!empty($p['transfer'])) {
            $out[] = ['method' => 'Transferencia', 'detail' => (string) $p['transfer']];
        }
        if (!empty($p['cash'])) {
            $out[] = ['method' => 'Efectivo', 'detail' => 'Pagas al recibir el pedido'];
        }
        if (!empty($p['card'])) {
            $out[] = ['method' => 'Tarjeta', 'detail' => 'Pagas con POS al recibir el pedido'];
        }

        return $out;
    }

    /** Texto plano (WhatsApp, correo): una línea por medio. */
    public static function plain(?array $p): string
    {
        $lines = array_map(fn ($i) => $i['method'] . ': ' . $i['detail'], self::instructions($p));
        if (!empty($p['notes'])) {
            $lines[] = (string) $p['notes'];
        }

        return implode("\n", $lines);
    }

    /** 987 654 321 — un celular se lee mejor en grupos de tres. */
    private static function phone(string $raw): string
    {
        $d = preg_replace('/\D+/', '', $raw);
        if (strlen($d) === 9) {
            return substr($d, 0, 3) . ' ' . substr($d, 3, 3) . ' ' . substr($d, 6);
        }

        return $raw;
    }
}
