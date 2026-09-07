<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Order;
use App\Models\Tenant\ShippingRequest;
use App\Services\Tenant\OrderShipmentLinker;
use Illuminate\Http\Request;

/**
 * Acciones sobre el ENVÍO de un pedido, invocables desde Pedidos.
 *
 * Cambiar la modalidad de entrega, anular un envío y restaurarlo ya existían y
 * funcionaban en el panel de Envíos. Lo que faltaba era poder hacerlo sin salir
 * de Pedidos, que es donde el operador está mirando el pedido.
 *
 * ── Adaptador, no segunda implementación ─────────────────────────────────
 *
 * Estas acciones tienen reglas que no son evidentes: cambiar de modalidad
 * revalida los datos obligatorios de la modalidad nueva y bloquea si el envío
 * ya está en un lote impreso (salvo excepción de administrador, que queda
 * auditada); anular arrastra motivo y bitácora; restaurar solo aplica a un
 * envío anulado. Reescribir eso aquí sería tener dos verdades.
 *
 * Así que este controlador solo resuelve el envío del pedido y reenvía a
 * `ShipmentController`, traduciendo su respuesta —que es una redirección con
 * mensaje en sesión, porque el panel de Envíos son formularios— a JSON, que es
 * lo que la pantalla Vue de Pedidos sabe consumir.
 */
class OrderShipmentActionController extends Controller
{
    public function modalidad(Request $request, Order $order)
    {
        return $this->reenviar($order, fn ($envio) =>
            app(ShipmentController::class)->changeModality($request, $envio));
    }

    public function anular(Request $request, Order $order)
    {
        return $this->reenviar($order, fn ($envio) =>
            app(ShipmentController::class)->cancel($request, $envio));
    }

    public function restaurar(Request $request, Order $order)
    {
        // Restaurar es el único que trabaja sobre un envío ANULADO, así que no
        // puede pedir el vigente: `current()` los ignora por definición.
        return $this->reenviar($order, fn ($envio) =>
            app(ShipmentController::class)->restore($request, $envio), true);
    }

    /**
     * Sube la guía de la agencia.
     *
     * El archivo viaja en el mismo `$request` que se reenvía, asi que
     * `uploadGuide` lo recibe igual que desde su propio formulario. No se
     * reimplementa nada: la validacion del tipo y tamaño, el guardado en el
     * disco del tenant, el cambio de estado a «enviado» y el sello de
     * `sent_at` siguen siendo suyos.
     */
    public function guia(Request $request, Order $order)
    {
        return $this->reenviar($order, fn ($envio) =>
            app(ShipmentController::class)->uploadGuide($request, $envio));
    }

    /**
     * Resuelve el envío del pedido, ejecuta la acción y traduce la respuesta.
     *
     * @param bool $incluirAnulados Para restaurar, que actúa justo sobre esos.
     */
    private function reenviar(Order $order, callable $accion, bool $incluirAnulados = false)
    {
        $envio = $incluirAnulados
            ? ShippingRequest::where('order_id', $order->id)->latest('id')->first()
            : app(OrderShipmentLinker::class)->current($order);

        if (!$envio) {
            return response()->json([
                'success' => false,
                'message' => 'Este pedido todavía no tiene envío configurado.',
            ], 422);
        }

        $respuesta = $accion($envio);

        return response()->json($this->traducir($respuesta));
    }

    /**
     * El panel de Envíos responde con `back()->with('success'|'error')`. Aquí
     * interesa el resultado, no la redirección: se lee de la sesión para no
     * perder el motivo exacto del rechazo, que es lo único que le dice al
     * operador qué corregir.
     */
    private function traducir($respuesta): array
    {
        if ($respuesta instanceof \Illuminate\Http\JsonResponse) {
            return (array) $respuesta->getData(true);
        }

        if ($respuesta instanceof \Illuminate\Http\RedirectResponse) {
            $sesion = $respuesta->getSession();
            $error  = $sesion ? $sesion->get('error') : null;

            return [
                'success' => !$error,
                'message' => $error ?: ($sesion ? $sesion->get('success') : 'Hecho.'),
            ];
        }

        return ['success' => true, 'message' => 'Hecho.'];
    }
}
