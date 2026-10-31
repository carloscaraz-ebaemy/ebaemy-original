<?php

namespace App\Services\Tenant;

use App\Models\System\ShalomAgency;
use App\Models\Tenant\ShippingAuditLog;
use App\Models\Tenant\ShippingRequest;
use App\Models\Tenant\ShippingSetting;
use App\Services\System\ShalomApi;
use App\Services\System\ShalomPlan;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Cambios sobre una guía de Shalom YA creada (autogestiones del API).
 *
 * La guía no se edita: Shalom permite cambiar la clave de recojo (un paso) y,
 * con un código que manda por SMS o email, la oficina de destino o quién
 * recoge. Cada cambio confirmado se copia al envío: si Shalom lleva el
 * paquete a una oficina y el panel dice otra, el rótulo y el aviso al cliente
 * mienten. Por eso `ShalomTracking::guardChange` bloquea cambiar esos campos
 * por los formularios normales y este es el único camino.
 *
 * La cantidad de bultos NO es una autogestión: se cambia quitando la guía y
 * creándola de nuevo (solo mientras el paquete no llegó a la agencia).
 */
class ShalomGuideChanges
{
    /** Tipos que se ofrecen desde el panel. */
    public const TYPES = ['cambio_destino', 'cambiar_contacto'];

    public function __construct(private ShalomApi $api) {}

    /** Por qué no se puede gestionar esta guía, o null si se puede. */
    public static function blocker(ShippingRequest $s): ?string
    {
        if (!ShalomPlan::allowsInstances()) {
            return 'El plan de Shalom de ebaemy no incluye gestionar guías.';
        }
        if (empty(ShippingSetting::current()->shalom_instance_id)) {
            return 'Conecta tu cuenta de Shalom Pro en la política de envío.';
        }
        if (!ShalomTracking::applies($s)) {
            return 'La guía todavía no tiene número de Shalom. Aparece en unos minutos después de crearla.';
        }
        if ($s->carrier_status === 'entregado') {
            return 'El paquete ya se entregó: no hay nada que cambiar.';
        }

        return null;
    }

    public function changeClave(ShippingRequest $s, string $clave): void
    {
        $this->assertManageable($s);
        $this->api->changePickupCode($this->instance(), $s->tracking_number, $s->tracking_code, $clave);

        $ce = $s->carrier_events ?? [];
        $antes = $ce['clave'] ?? null;
        $ce['clave'] = $clave;
        $s->forceFill(['carrier_events' => $ce])->save();

        ShippingAuditLog::log(ShippingAuditLog::ACTION_EDIT, $s->id, 'shalom_clave', $antes, $clave, 'Clave de recojo cambiada en Shalom');
    }

    /**
     * Primer paso: Shalom guarda el cambio y manda el código al teléfono.
     *
     * @param array{destino?:int, destinatario?:string, nombre?:string} $params
     * @return array{challenge:string, canal:string, expira:?string}
     */
    public function start(ShippingRequest $s, string $tipo, string $telefono, array $params): array
    {
        $this->assertManageable($s);
        if (!in_array($tipo, self::TYPES, true)) {
            throw new RuntimeException('Ese cambio no se puede hacer desde aquí.');
        }

        $data = ['guia' => $s->tracking_number, 'codigo' => $s->tracking_code, 'tipo' => $tipo, 'telefono' => $telefono];
        $local = [];

        if ($tipo === 'cambio_destino') {
            $office = ShalomAgency::selectable()->find((int) ($params['destino'] ?? 0));
            if (!$office) {
                throw new RuntimeException('Elige una oficina de Shalom de la lista.');
            }
            if ((int) $office->ter_id === (int) $s->agency_office_id) {
                throw new RuntimeException('El paquete ya va a esa oficina.');
            }
            $data['destino'] = (int) $office->ter_id;
            // Shalom solo lo toma solo con el envío pendiente; si el paquete ya
            // salió hay que mandarlo, o responde 400 (pasó con la guía en camino).
            // Es el documento con que se creó la guía: el del cliente, o el del
            // autorizado si el cliente es empresa.
            $esEmpresa = strlen(preg_replace('/\D/', '', (string) $s->dni)) === 11;
            $doc = preg_replace('/\D/', '', (string) ($esEmpresa ? $s->pickup_person_dni : $s->dni));
            if ($doc !== '') {
                $data['destinatario'] = $doc;
            }
            $local = ['destino' => (int) $office->ter_id];
        } else {
            $doc = preg_replace('/\D/', '', (string) ($params['destinatario'] ?? ''));
            if (!preg_match('/^\d{8}$|^\d{11}$/', $doc)) {
                throw new RuntimeException('El documento de quien recoge debe tener 8 dígitos (DNI) u 11 (RUC).');
            }
            $data['destinatario'] = $doc;
            $local = ['destinatario' => $doc, 'nombre' => trim((string) ($params['nombre'] ?? ''))];
        }

        try {
            $r = $this->api->startSelfManagement($this->instance(), $data);
        } catch (RuntimeException $e) {
            // Con el paquete en ruta Shalom no deja cambiar el destino: se hace
            // cuando llega a la oficina de destino. Su texto no lo dejaba claro.
            if ($tipo === 'cambio_destino' && stripos($e->getMessage(), 'no se encuentra en destino') !== false) {
                throw new RuntimeException('Shalom deja cambiar la oficina cuando el paquete ya llegó a su destino actual ('
                    . ($s->agency_office_name ?: 'la oficina de destino') . '). Ahora va en camino: inténtalo cuando llegue.');
            }
            throw $e;
        }
        if (empty($r['challengeId'])) {
            throw new RuntimeException('Shalom no envió el código: ' . ($r['message'] ?? $r['error'] ?? 'sin detalle'));
        }

        // Lo que hay que copiar al envío cuando se confirme. El challenge es de
        // Shalom; aquí solo se recuerda a qué envío y a qué cambio pertenece.
        Cache::put($this->cacheKey($r['challengeId']), ['shipment' => $s->id, 'tipo' => $tipo] + $local, now()->addMinutes(15));

        return ['challenge' => $r['challengeId'], 'canal' => $r['canal'] ?? 'sms', 'expira' => $r['expiresAt'] ?? null];
    }

    /** Segundo paso: el código recibido. Aplica en Shalom y copia al envío. */
    public function confirm(ShippingRequest $s, string $challenge, string $code): string
    {
        $pend = Cache::get($this->cacheKey($challenge));
        if (!$pend || (int) $pend['shipment'] !== (int) $s->id) {
            throw new RuntimeException('El código venció o no es de este envío. Vuelve a pedirlo.');
        }

        $r = $this->api->confirmSelfManagement($this->instance(), $challenge, strtoupper(trim($code)));
        if (isset($r['success']) && $r['success'] === false) {
            throw new RuntimeException('Shalom no aceptó el código: ' . ($r['message'] ?? $r['error'] ?? 'revísalo'));
        }
        Cache::forget($this->cacheKey($challenge));

        if ($pend['tipo'] === 'cambio_destino') {
            $office = ShalomAgency::find((int) $pend['destino']) ?: ShalomAgency::where('ter_id', (int) $pend['destino'])->first();
            $antes = $s->agency_office_name;
            $s->forceFill(array_filter([
                'agency_office_id'      => (int) $pend['destino'],
                'agency_office_name'    => $office->name ?? null,
                'agency_office_address' => $office->address ?? null,
                'reference'             => $office->name ?? null,
                'destination_city'      => $office->zone ?? null,
            ], fn ($v) => $v !== null))->save();
            ShippingAuditLog::log(ShippingAuditLog::ACTION_EDIT, $s->id, 'agency_office_id', $antes, $office->name ?? $pend['destino'], 'Oficina de destino cambiada en Shalom');

            return 'Listo: Shalom llevará el paquete a la oficina ' . ($office->name ?? $pend['destino']) . '.';
        }

        $antes = $s->pickup_person_dni;
        $s->forceFill(array_filter([
            'pickup_person_dni'  => $pend['destinatario'],
            'pickup_person_name' => $pend['nombre'] ?: null,
        ], fn ($v) => $v !== null))->save();
        ShippingAuditLog::log(ShippingAuditLog::ACTION_EDIT, $s->id, 'pickup_person_dni', $antes, $pend['destinatario'], 'Quien recoge cambiado en Shalom');

        return 'Listo: ahora puede recoger el paquete la persona con documento ' . $pend['destinatario'] . '.';
    }

    private function assertManageable(ShippingRequest $s): void
    {
        if ($motivo = self::blocker($s)) {
            throw new RuntimeException($motivo);
        }
    }

    private function instance(): string
    {
        return (string) ShippingSetting::current()->shalom_instance_id;
    }

    private function cacheKey(string $challenge): string
    {
        return 'shalom_sm:' . $challenge;
    }
}
