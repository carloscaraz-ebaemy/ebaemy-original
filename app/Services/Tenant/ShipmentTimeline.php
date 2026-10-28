<?php

namespace App\Services\Tenant;

use App\Models\Tenant\ShippingRequest as SR;
use Carbon\Carbon;

/**
 * Dónde está el pedido, contado para el CLIENTE (página de seguimiento).
 *
 * Junta dos fuentes que antes se mostraban por separado: el estado interno
 * del almacén (recibido, embalando, impreso…, que al cliente no le dicen
 * nada) y los hitos de la agencia (ShalomTracking: en camino, en la oficina
 * de destino, entregado). El resultado es una frase de dónde está ahora y una
 * línea de tiempo en palabras del cliente, distinta por modalidad.
 *
 * Solo lee: no cambia ningún estado.
 */
class ShipmentTimeline
{
    /** Estados del almacén que significan «lo estamos preparando». */
    private const PREPARANDO = [SR::STATUS_CONFIRMADO, SR::STATUS_PREPARANDO, SR::STATUS_IMPRESO, SR::STATUS_EMBALANDO];

    /**
     * @return array{tone:string, title:string, detail:?string, eta:?string, delay:bool,
     *               steps:array<int,array{label:string, hint:?string, at:?Carbon, state:string}>,
     *               office:?array, cancelled:bool}
     */
    public function for(SR $s): array
    {
        if ($s->status === SR::STATUS_ANULADO) {
            return ['tone' => 'muted', 'title' => 'Este envío fue anulado', 'detail' => 'Escríbele a la tienda si crees que es un error.',
                    'eta' => null, 'delay' => false, 'steps' => [], 'office' => null, 'cancelled' => true];
        }

        // Pedido abierto: el cliente sigue agregando productos.
        if ($s->isOnHold()) {
            return ['tone' => 'wait', 'title' => 'Estamos guardando tu pedido',
                    'detail' => 'Puedes seguir agregando productos. Cuando nos avises que ya no pides más, lo preparamos y te lo enviamos.',
                    'eta' => null, 'delay' => false,
                    'steps' => [
                        ['label' => 'Pedido registrado', 'hint' => null, 'at' => $s->created_at, 'state' => 'done'],
                        ['label' => 'Guardando tu pedido', 'hint' => 'Desde el ' . $this->dia($s->on_hold_since) . '.', 'at' => $s->on_hold_since, 'state' => 'current'],
                        ['label' => 'Lo preparamos y te lo enviamos', 'hint' => null, 'at' => null, 'state' => 'todo'],
                    ],
                    'office' => $this->oficina($s), 'cancelled' => false];
        }

        $ce = $s->carrier_events ?? [];
        $hito = [];
        foreach ((array) ($ce['events'] ?? []) as $ev) {
            if (!empty($ev['key']) && !empty($ev['at'])) {
                $hito[$ev['key']] = $this->fecha($ev['at']);
            }
        }
        $carrier = $s->carrier_status;
        $entregado = $s->status === SR::STATUS_ENTREGADO || $carrier === 'entregado';
        $fechaEntrega = $hito['entregado'] ?? $s->picked_up_at ?? ($entregado ? optional($s->order)->delivered_at : null);

        return match ($s->delivery_type) {
            SR::DELIVERY_TIENDA    => $this->tienda($s, $entregado, $fechaEntrega),
            SR::DELIVERY_DOMICILIO => $this->domicilio($s, $entregado, $fechaEntrega),
            default                => $this->agencia($s, $ce, $hito, $carrier, $entregado, $fechaEntrega),
        };
    }

    private function agencia(SR $s, array $ce, array $hito, ?string $carrier, bool $entregado, ?Carbon $fechaEntrega): array
    {
        $agencia = $s->shipping_agency ?: 'la agencia';
        $ciudad = $s->destination_city ?: ($ce['dest'] ?? null);
        $salio = in_array($s->status, [SR::STATUS_DESPACHADO, SR::STATUS_EN_AGENCIA, SR::STATUS_EN_RUTA], true)
            || in_array($carrier, ['origen', 'transito', 'destino', 'reparto'], true) || $entregado;
        $conHitos = !empty($ce['events']);

        // Etapa actual (índice de la línea de tiempo de abajo).
        $etapa = 0;
        if (in_array($s->status, self::PREPARANDO, true)) $etapa = 1;
        if ($salio) $etapa = 2;
        if ($carrier === 'transito' || ($s->status === SR::STATUS_EN_RUTA && !$conHitos)) $etapa = 3;
        if (in_array($carrier, ['destino', 'reparto'], true)) $etapa = 4;
        if ($entregado) $etapa = 5;

        $pasos = [
            ['Pedido registrado', null, $s->created_at],
            ['Lo estamos preparando', null, $s->printed_at],
            ['Entregado a ' . $agencia, null, $hito['origen'] ?? $s->sent_at],
            ['En camino' . ($ciudad ? ' a ' . $ciudad : ''), $conHitos ? null : 'La agencia lo lleva a tu ciudad.', $hito['transito'] ?? null],
            [$carrier === 'reparto' ? 'En reparto a tu domicilio' : 'Listo para recoger en tu oficina', null, $hito['reparto'] ?? $hito['destino'] ?? null],
            ['Entregado', null, $fechaEntrega],
        ];

        [$tono, $titulo, $detalle] = match ($etapa) {
            5 => ['ok', 'Tu pedido fue entregado', $fechaEntrega ? 'El ' . $this->dia($fechaEntrega) . '.' : null],
            4 => $carrier === 'reparto'
                ? ['ok', 'Tu pedido está en reparto', $agencia . ' lo lleva a tu domicilio.']
                : ['ok', 'Tu pedido ya llegó a tu oficina', 'Recógelo con tu documento de identidad' . ($s->agency_office_name ? ' en ' . $s->agency_office_name : '') . '.'],
            3 => ['go', 'Tu pedido está en camino' . ($ciudad ? ' a ' . $ciudad : ''), $agencia . ' lo está llevando.'],
            2 => ['go', 'Tu pedido ya salió', 'Lo entregamos a ' . $agencia . ($s->tracking_number ? ' con la guía ' . $s->tracking_number : '') . '.'],
            1 => ['wait', 'Estamos preparando tu pedido', 'Te avisaremos cuando lo entreguemos a ' . $agencia . '.'],
            default => ['wait', 'Recibimos tu pedido', 'Lo revisamos y lo preparamos para enviarlo.'],
        };

        return [
            'tone' => $tono, 'title' => $titulo, 'detail' => $detalle,
            'eta' => $etapa >= 2 && $etapa < 4 ? ($ce['eta'] ?? null) : null,
            'delay' => !empty($ce['delay']) && $etapa < 5,
            'steps' => $this->pasos($pasos, $etapa),
            'office' => $this->oficina($s),
            'cancelled' => false,
        ];
    }

    private function domicilio(SR $s, bool $entregado, ?Carbon $fechaEntrega): array
    {
        $etapa = 0;
        if (in_array($s->status, self::PREPARANDO, true)) $etapa = 1;
        if (in_array($s->status, [SR::STATUS_ASIGNADO, SR::STATUS_EN_CAMINO, SR::STATUS_DESPACHADO, SR::STATUS_EN_RUTA], true)) $etapa = 2;
        if ($entregado) $etapa = 3;

        $pasos = [
            ['Pedido registrado', null, $s->created_at],
            ['Lo estamos preparando', null, $s->printed_at],
            ['En camino a tu domicilio', 'Un motorizado lo lleva hasta tu puerta.', $s->sent_at],
            ['Entregado', null, $fechaEntrega],
        ];
        [$tono, $titulo, $detalle] = match ($etapa) {
            3 => ['ok', 'Tu pedido fue entregado', $fechaEntrega ? 'El ' . $this->dia($fechaEntrega) . '.' : null],
            2 => ['go', 'Tu pedido está en camino', 'El motorizado va hacia tu dirección' . ($s->duration_text ? ' (unos ' . $s->duration_text . ' desde la tienda)' : '') . '.'],
            1 => ['wait', 'Estamos preparando tu pedido', 'Te avisaremos cuando salga el motorizado.'],
            default => ['wait', 'Recibimos tu pedido', 'Lo revisamos y lo preparamos para el reparto.'],
        };

        return ['tone' => $tono, 'title' => $titulo, 'detail' => $detalle, 'eta' => null, 'delay' => false,
                'steps' => $this->pasos($pasos, $etapa), 'office' => null, 'cancelled' => false];
    }

    private function tienda(SR $s, bool $entregado, ?Carbon $fechaEntrega): array
    {
        $etapa = 0;
        if (in_array($s->status, self::PREPARANDO, true)) $etapa = 1;
        if ($s->status === SR::STATUS_LISTO_RECOJO) $etapa = 2;
        if ($entregado) $etapa = 3;

        $pasos = [
            ['Pedido registrado', null, $s->created_at],
            ['Lo estamos preparando', null, $s->printed_at],
            ['Listo para recoger en la tienda', null, $s->ready_at],
            ['Recogido', null, $fechaEntrega],
        ];
        [$tono, $titulo, $detalle] = match ($etapa) {
            3 => ['ok', 'Ya recogiste tu pedido', $fechaEntrega ? 'El ' . $this->dia($fechaEntrega) . '.' : null],
            2 => ['ok', 'Tu pedido está listo para recoger', 'Acércate a la tienda con tu documento de identidad.'],
            1 => ['wait', 'Estamos preparando tu pedido', 'Te avisaremos cuando puedas recogerlo.'],
            default => ['wait', 'Recibimos tu pedido', 'Te avisaremos cuando esté listo para recoger.'],
        };

        return ['tone' => $tono, 'title' => $titulo, 'detail' => $detalle, 'eta' => null, 'delay' => false,
                'steps' => $this->pasos($pasos, $etapa), 'office' => null, 'cancelled' => false];
    }

    /** @param array<int,array{0:string,1:?string,2:mixed}> $pasos */
    private function pasos(array $pasos, int $etapa): array
    {
        $out = [];
        foreach ($pasos as $i => [$label, $hint, $at]) {
            $out[] = [
                'label' => $label,
                'hint'  => $i === $etapa ? $hint : null,
                // Un paso ya pasado sin fecha registrada se muestra hecho igual.
                'at'    => $i <= $etapa ? $this->fecha($at) : null,
                'state' => $i < $etapa ? 'done' : ($i === $etapa ? 'current' : 'todo'),
            ];
        }

        return $out;
    }

    /** Oficina de la agencia donde recoge: nombre, dirección, horario y cómo llegar. */
    private function oficina(SR $s): ?array
    {
        $nombre = $s->agency_office_name ?? null;
        if (!$nombre) {
            return null;
        }
        $o = null;
        if (!empty($s->agency_office_id)) {
            $o = rescue(fn () => \App\Models\System\ShalomAgency::find((int) $s->agency_office_id), null, false);
        }
        $dir = $s->agency_office_address ?? ($o->address ?? null);
        $maps = $o && $o->latitude && $o->longitude
            ? 'https://www.google.com/maps/search/?api=1&query=' . $o->latitude . ',' . $o->longitude
            : ($dir ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($s->shipping_agency . ' ' . $nombre . ' ' . $dir) : null);

        return ['agency' => $s->shipping_agency, 'name' => $nombre, 'address' => $dir, 'hours' => $o->hours ?? null, 'maps' => $maps];
    }

    private function fecha($v): ?Carbon
    {
        if (!$v) return null;
        try {
            return $v instanceof Carbon ? $v : Carbon::parse($v);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function dia(Carbon $c): string
    {
        return $c->copy()->locale('es')->isoFormat('dddd D [de] MMMM');
    }
}
