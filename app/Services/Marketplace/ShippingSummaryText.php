<?php

namespace App\Services\Marketplace;

/**
 * Frases para el comprador a partir del resumen de envío de una tienda
 * (StoreShipping::summary(), guardado en marketplace_listings.shipping).
 *
 * Una sola redacción para la ficha del marketplace, el carrito, el checkout y la
 * tienda online del tenant: si el texto cambia, cambia aquí. Clase pura, sin
 * BD, para poder probarla y usarla desde la base system o la del tenant.
 *
 * Regla de oro: no prometer nada que la tienda no haya dicho. Sin resumen
 * (tienda que no confirmó) → «A coordinar con la tienda».
 */
class ShippingSummaryText
{
    public const PENDING = 'A coordinar con la tienda';

    /**
     * Líneas «Destino → cómo y cuándo» para la ficha.
     *
     * @return array<int,array{key:string,title:string,price:string,detail:string,free:bool}>
     */
    public static function lines(?array $s): array
    {
        if (!$s) {
            return [];
        }

        $out = [];

        if (!empty($s['lima']['mode'])) {
            $l = $s['lima'];
            $out[] = [
                'key'    => 'lima',
                'title'  => 'Lima y Callao',
                'price'  => self::price($l['mode'], $l['cost'] ?? null),
                'detail' => self::days($l['days'] ?? null, 'Llega en'),
                'free'   => $l['mode'] === 'free',
            ];
        }

        if (!empty($s['province']['mode'])) {
            $p = $s['province'];
            $price = $p['mode'] === 'destination'
                ? 'Pago en destino'
                : self::price($p['mode'], $p['cost'] ?? null);

            $detalle = [];
            if ($p['mode'] === 'destination') {
                $fee = $p['agency_fee'] ?? null;
                if ($fee !== null) {
                    $detalle[] = (float) $fee > 0
                        ? 'Llevarlo a la agencia: ' . self::soles($fee)
                        : 'Llevarlo a la agencia es gratis';
                }
            }
            $plazo = [];
            if (isset($p['handling']) && $p['handling'] !== null) {
                $plazo[] = 'Sale a la agencia en ' . self::n((int) $p['handling'], 'día hábil', 'días hábiles');
            }
            if (!empty($p['transit'])) {
                $plazo[] = self::range($p['transit']) . ' de viaje';
            }
            if ($plazo) {
                $detalle[] = implode(' + ', $plazo);
            }
            if (!empty($p['agencies'])) {
                $detalle[] = 'Vía ' . implode(', ', array_slice($p['agencies'], 0, 3));
            }

            $out[] = [
                'key'    => 'province',
                'title'  => 'Provincias',
                'price'  => $price,
                'detail' => implode(' · ', $detalle),
                'free'   => $p['mode'] === 'free',
            ];
        }

        if (!empty($s['pickup'])) {
            $k = $s['pickup'];
            $donde = !empty($k['district']) ? 'en ' . $k['district'] : 'en la tienda';
            $out[] = [
                'key'    => 'pickup',
                'title'  => 'Recojo ' . $donde,
                'price'  => 'Gratis',
                'detail' => !empty($k['hours']) ? 'Listo en ' . self::n((int) $k['hours'], 'hora', 'horas') : '',
                'free'   => true,
            ];
        }

        return $out;
    }

    /** Notas generales bajo las líneas: envío gratis por monto y hora de corte. */
    public static function notes(?array $s): array
    {
        if (!$s) {
            return [];
        }
        $notas = [];
        if (!empty($s['free_from']) && (float) $s['free_from'] > 0) {
            $notas[] = 'Envío gratis en compras desde ' . self::soles($s['free_from'])
                . ((($s['province']['mode'] ?? null) === 'destination') ? ' (el flete a provincias se paga en destino)' : '');
        }
        if (!empty($s['cutoff'])) {
            $notas[] = 'Pedidos antes de las ' . $s['cutoff'] . ' salen el mismo día hábil';
        }

        return $notas;
    }

    /**
     * «Cambios hasta 7 días por falla · cambios de talla o color en 7 días».
     * null si la tienda no definió su política de cambios.
     */
    public static function returns(?array $s): ?string
    {
        $r = $s['returns'] ?? null;
        if (!$r || empty($r['defect_days'])) {
            return null;
        }
        $txt = 'Cambios por falla hasta ' . self::n((int) $r['defect_days'], 'día', 'días');
        if (!empty($r['change_days'])) {
            $txt .= ' · por talla o color hasta ' . self::n((int) $r['change_days'], 'día', 'días');
        }

        return $txt;
    }

    /**
     * La única etiqueta que va en la card del listado, y solo si es cierta.
     * Más de una satura la card y deja de leerse.
     */
    public static function badge(?array $s): ?string
    {
        $lima = $s['lima']['mode'] ?? null;
        $prov = $s['province']['mode'] ?? null;
        if ($lima === 'free' && $prov === 'free') {
            return 'Envío gratis a todo el Perú';
        }
        if ($lima === 'free') {
            return 'Envío gratis en Lima';
        }

        return null;
    }

    /** Una línea corta para el carrito: «Lima S/ 10 · Provincias pago en destino». */
    public static function short(?array $s): string
    {
        if (!$s) {
            return self::PENDING;
        }
        $partes = [];
        foreach (self::lines($s) as $l) {
            if ($l['key'] === 'pickup') {
                continue;
            }
            // «Pago en destino» → «pago en destino», pero «S/ 10.00» se queda igual.
            $precio = str_starts_with($l['price'], 'S/')
                ? $l['price']
                : mb_strtolower(mb_substr($l['price'], 0, 1)) . mb_substr($l['price'], 1);
            $partes[] = ($l['key'] === 'lima' ? 'Lima' : 'Provincias') . ' ' . $precio;
        }

        return $partes ? implode(' · ', $partes) : self::PENDING;
    }

    /**
     * El envío de un paquete a UN destino, para el checkout: el comprador vive
     * en un solo sitio y no tiene por qué leer las tarifas de Lima y de
     * provincias a la vez («Lima S/ 0.00 · Provincias pago en destino»).
     *
     * $zone: 'lima' (Lima Metropolitana o Callao) o 'province'.
     * amount: lo que se suma al total estimado (null = no se sabe aún).
     * pending: queda una parte que confirma la tienda (cotización, flete en agencia).
     *
     * @return array{text:string,detail:string,amount:?float,pending:bool,free:bool}
     */
    public static function forZone(?array $s, string $zone, float $subtotal = 0): array
    {
        $porConfirmar = ['text' => 'Te lo confirma la tienda', 'detail' => '', 'amount' => null, 'pending' => true, 'free' => false];
        $z = $s[$zone === 'lima' ? 'lima' : 'province'] ?? null;
        if (!$s || empty($z['mode'])) {
            return $porConfirmar;
        }

        $mode = $z['mode'];
        $cost = isset($z['cost']) ? (float) $z['cost'] : null;
        $freeFrom = (float) ($s['free_from'] ?? 0);
        $plazo = $zone === 'lima'
            ? self::days($z['days'] ?? null, 'Llega en')
            : (!empty($z['transit']) ? 'Llega en ' . mb_strtolower(self::range($z['transit'])) . ' de viaje' : '');

        if ($mode === 'destination') {
            $fee = (float) ($z['agency_fee'] ?? 0);
            $agencias = !empty($z['agencies']) ? ' por ' . implode(', ', array_slice($z['agencies'], 0, 2)) : '';
            return [
                'text'    => $fee > 0 ? self::soles($fee) . ' + flete al recoger' : 'Pagas el flete al recoger',
                'detail'  => trim('Lo envía' . $agencias . ' y pagas el flete en la agencia. ' . $plazo),
                'amount'  => $fee,
                'pending' => true,
                'free'    => false,
            ];
        }

        // «Fijo S/ 0» es gratis aunque la tienda no eligiera «gratis»: mostrar
        // «S/ 0.00» se leia como «no se sabe».
        $gratis = $mode === 'free'
            || ($mode === 'fixed' && (float) $cost <= 0)
            || ($freeFrom > 0 && $subtotal >= $freeFrom);
        if ($gratis) {
            return ['text' => 'Gratis', 'detail' => $plazo, 'amount' => 0.0, 'pending' => false, 'free' => true];
        }
        if ($mode === 'fixed') {
            $falta = $freeFrom > 0 ? ' · Gratis desde ' . self::soles($freeFrom) : '';
            return ['text' => self::soles($cost), 'detail' => trim($plazo . $falta, ' ·'), 'amount' => $cost, 'pending' => false, 'free' => false];
        }
        if ($mode === 'quote') {
            return [
                'text'    => $cost > 0 ? 'Desde ' . self::soles($cost) : 'Te lo cotiza la tienda',
                'detail'  => trim(($cost > 0 ? 'La tienda te confirma el precio exacto. ' : '') . $plazo),
                'amount'  => $cost > 0 ? $cost : null,
                'pending' => true,
                'free'    => false,
            ];
        }

        return $porConfirmar;
    }

    private static function price(string $mode, $cost): string
    {
        return match ($mode) {
            'free'  => 'Gratis',
            'fixed' => self::soles($cost),
            'quote' => ($cost !== null && (float) $cost > 0) ? 'A cotizar, desde ' . self::soles($cost) : 'A cotizar',
            default => self::PENDING,
        };
    }

    private static function days(?array $range, string $prefijo): string
    {
        return $range ? $prefijo . ' ' . mb_strtolower(self::range($range)) : '';
    }

    private static function range(array $r): string
    {
        $a = (int) ($r[0] ?? 0);
        $b = (int) ($r[1] ?? $a);
        if ($b < $a) {
            [$a, $b] = [$b, $a];
        }
        if ($a === $b) {
            return $a === 0 ? 'El mismo día' : self::n($a, 'día hábil', 'días hábiles');
        }

        return $a . ' a ' . $b . ' días hábiles';
    }

    private static function n(int $n, string $uno, string $varios): string
    {
        return $n . ' ' . ($n === 1 ? $uno : $varios);
    }

    private static function soles($v): string
    {
        return 'S/ ' . number_format((float) $v, 2, '.', ',');
    }
}
