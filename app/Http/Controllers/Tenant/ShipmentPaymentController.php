<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ShippingPayment;
use App\Models\Tenant\ShippingRequest;
use Illuminate\Http\Request;

/**
 * Los pagos de un ENVÍO, hablando el contrato del panel de Nota de Venta.
 *
 * El dinero de un encargo logístico vive en `shipping_payments`, no en
 * `order_payments`. Hasta ahora, «Pagos del pedido» sobre un encargo abría la
 * pantalla de Envíos en otra pestaña: correcto en cuanto a la fuente de verdad,
 * pero sacaba al operador del listado y le hacía perder filtros, página y
 * posición. Con este adaptador el panel Vue de siempre —el mismo que usa Nota
 * de Venta— puede apuntar al envío sin salir de Pedidos.
 *
 * ── Por qué es un ADAPTADOR y no una segunda implementación ──────────────
 *
 * Registrar un pago de envío no es un `create()`. Hay reglas caras que ya viven
 * en `ShipmentController`: código de operación obligatorio, detección de
 * duplicados dentro del mismo envío y contra otros envíos (con forzado
 * auditado), tope contra el saldo real, asiento en Finanzas y
 * `syncPaymentState`, que es lo que habilita el rotulado.
 *
 * Reescribir eso aquí sería tener dos verdades sobre el mismo cobro. Así que
 * este controlador SOLO traduce nombres y reenvía. Las reglas siguen estando en
 * un único sitio, y la pantalla de Envíos y esta comparten comportamiento por
 * construcción, no por disciplina.
 *
 * ── La traducción ────────────────────────────────────────────────────────
 *
 * El panel habla el vocabulario de Nota de Venta y el envío tiene el suyo:
 *
 *   payment          → amount
 *   date_of_payment  → paid_at
 *   reference        → payment_code   (aquí NO es opcional: es la clave
 *                                      anti-duplicados del módulo de envíos)
 */
class ShipmentPaymentController extends Controller
{
    use \Modules\Finance\Traits\FinanceTrait;

    /** Catálogos del formulario: los mismos que Nota de Venta. */
    public function tables()
    {
        return [
            'payment_method_types' => \App\Models\Tenant\PaymentMethodType::all(),
            'payment_destinations' => $this->getPaymentDestinations(),
        ];
    }

    /**
     * Cabecera del panel, con las claves que espera el componente.
     *
     * `amount_to_collect` y `total_difference` pueden ser null en el envío
     * —nadie cargó el monto— pero el panel espera números. Se envía 0 y se
     * distingue el caso real con `has_manual_amount`: decir «resta S/ 0.00»
     * cuando no se sabe cuánto se debe sería afirmar que está saldado.
     */
    public function summary($id)
    {
        $s = ShippingRequest::findOrFail((int) $id);

        return [
            'total'             => round((float) ($s->amount_to_collect ?? 0), 2),
            'amount_due'        => $s->amount_due !== null ? round((float) $s->amount_due, 2) : null,
            'amount_to_collect' => round((float) ($s->amount_to_collect ?? 0), 2),
            'has_manual_amount' => $s->has_amount,
            'total_paid'        => round((float) $s->paid_total, 2),
            'total_difference'  => round((float) ($s->pending_total ?? 0), 2),
            'paid'              => $s->is_fully_paid,
        ];
    }

    /** Pagos ya registrados, en la forma que pinta la grilla. */
    public function records($id)
    {
        $rows = ShippingPayment::where('shipment_id', (int) $id)
            ->with(['payment_method_type', 'payment_file'])
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get()
            ->map(function (ShippingPayment $p) {
                return [
                    'id'                              => $p->id,
                    'code'                            => 'PAGO-' . $p->id,
                    'date_of_payment'                 => optional($p->paid_at ?: $p->created_at)->format('Y-m-d'),
                    'payment_method_type_id'          => $p->payment_method_type_id,
                    'payment_method_type_description' => $p->method_label,
                    'payment_destination_id'          => $p->payment_destination_id,
                    'destination_description'         => $this->describeDestination($p->payment_destination_id),
                    // El código de operación se presenta como «referencia»: es
                    // la casilla que el panel ya tiene y significa lo mismo.
                    'reference'                       => $p->payment_code,
                    'filename'                        => optional($p->payment_file)->filename,
                    'payment'                         => (float) $p->amount,
                ];
            });

        return ['data' => $rows];
    }

    /**
     * Registra el pago reenviando a `ShipmentController::storePayment`, que es
     * quien tiene las reglas. Aquí solo se traduce la entrada y la salida.
     */
    public function store(Request $request)
    {
        $shipment = ShippingRequest::findOrFail((int) $request->input('shipment_id'));

        // El código es obligatorio en envíos y opcional en el panel. Se avisa
        // antes de reenviar para que el mensaje hable del campo que el operador
        // tiene delante, y no del nombre interno.
        $codigo = trim((string) $request->input('reference'));
        if ($codigo === '') {
            return [
                'success' => false,
                'message' => 'Indica el código de la operación en «Referencia»: es lo que permite detectar el mismo voucher cargado dos veces.',
            ];
        }

        $request->merge([
            'amount'          => $request->input('payment'),
            'payment_code'    => $codigo,
            'date_of_payment' => $request->input('date_of_payment'),
        ]);

        // `paymentResponse` solo devuelve JSON si el request lo pide. Desde
        // aqui SIEMPRE lo queremos: sin esto llegaria una redireccion y habria
        // que adivinar el resultado leyendo la sesion.
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->headers->set('Accept', 'application/json');

        $respuesta = app(ShipmentController::class)->storePayment($request, $shipment);
        $cuerpo    = $this->cuerpoJson($respuesta);

        return [
            'success' => (bool) ($cuerpo['success'] ?? false),
            'message' => $cuerpo['message'] ?? 'No se pudo registrar el pago.',
            'summary' => $this->summary($shipment->id),
        ];
    }

    /** Monto total a cobrar del envío. */
    public function storeAmountDue(Request $request, $id)
    {
        $shipment = ShippingRequest::findOrFail((int) $id);

        // `updateAmountDue` exige la clave presente aunque venga vacía: vaciar
        // el monto y no haberlo cargado nunca son cosas distintas.
        $request->merge(['amount_due' => $request->input('amount_due')]);

        $respuesta = app(ShipmentController::class)->updateAmountDue($request, $shipment);
        $cuerpo    = $this->cuerpoJson($respuesta);

        return [
            'success' => (bool) ($cuerpo['success'] ?? true),
            'message' => $cuerpo['message'] ?? 'Monto actualizado.',
            'summary' => $this->summary($shipment->id),
        ];
    }

    /** Elimina un pago con su asiento, reenviando al módulo de envíos. */
    public function destroy($id)
    {
        $payment  = ShippingPayment::findOrFail((int) $id);
        $shipment = $payment->shipment;

        app(ShipmentController::class)->destroyPayment($shipment, $payment);

        return [
            'success' => true,
            'message' => 'Pago eliminado con éxito',
            'summary' => $this->summary($shipment->id),
        ];
    }

    /**
     * Nombre legible del destino del dinero (caja o cuenta bancaria).
     *
     * El helper equivalente del panel de Nota de Venta es privado de su trait,
     * así que se resuelve aquí sobre el MISMO catálogo de FinanceTrait: los
     * destinos que ve el operador son los mismos en las dos pantallas.
     */
    private function describeDestination($destinationId): ?string
    {
        if (!$destinationId) {
            return null;
        }

        static $catalogo = null;
        if ($catalogo === null) {
            $catalogo = collect($this->getPaymentDestinations())->keyBy('id');
        }

        $destino = $catalogo->get($destinationId);

        return $destino['description'] ?? ($destino->description ?? null);
    }

    /**
     * El módulo de envíos responde JSON o redirección según cómo se le llame.
     * Aquí interesa el cuerpo; si vino una redirección, se leen sus mensajes de
     * sesión para no perder el motivo del rechazo.
     */
    private function cuerpoJson($respuesta): array
    {
        if ($respuesta instanceof \Illuminate\Http\JsonResponse) {
            return (array) $respuesta->getData(true);
        }

        if ($respuesta instanceof \Illuminate\Http\RedirectResponse) {
            $session = $respuesta->getSession();
            $error   = $session ? $session->get('error') : null;

            return [
                'success' => !$error,
                'message' => $error ?: ($session ? $session->get('success') : null),
            ];
        }

        return ['success' => true, 'message' => null];
    }
}
