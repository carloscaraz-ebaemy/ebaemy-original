<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\OrderPayment;
use App\Models\Tenant\ShippingPayment;
use App\Services\Tenant\PaymentVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Verificar o rechazar un cobro.
 *
 * Sirve a los DOS sitios donde entra dinero —`order_payments` y
 * `shipping_payments`— porque la pregunta es la misma y la respuesta tiene que
 * serlo: el operador no puede tener que comprobar distinto según de dónde venga
 * el pedido.
 *
 * NO registra cobros ni toca importes. Registrar y comprobar son dos actos
 * distintos, los hacen dos personas distintas, y confundirlos es lo que este
 * trabajo viene a deshacer.
 */
class PaymentVerificationController extends Controller
{
    /** Las dos tablas, por su nombre corto en la ruta. */
    private const MODELOS = [
        'order'    => OrderPayment::class,
        'shipment' => ShippingPayment::class,
    ];

    public function verificar(Request $request, string $tipo, int $payment)
    {
        return $this->cambiar($request, $tipo, $payment, PaymentVerification::VERIFICADO);
    }

    public function rechazar(Request $request, string $tipo, int $payment)
    {
        return $this->cambiar($request, $tipo, $payment, PaymentVerification::RECHAZADO);
    }

    private function cambiar(Request $request, string $tipo, int $id, string $destino)
    {
        if (!isset(self::MODELOS[$tipo])) {
            return response()->json(['success' => false, 'message' => 'Tipo de cobro desconocido.'], 422);
        }

        $clase = self::MODELOS[$tipo];
        $tabla = (new $clase)->getTable();

        // Tenant con `tenancy:migrate` atrasado: mejor decirlo que fallar con
        // un 1054 que el operador no puede interpretar.
        if (!Schema::connection('tenant')->hasColumn($tabla, 'verification_status')) {
            return response()->json([
                'success' => false,
                'message' => 'Este tenant todavía no tiene la verificación de cobros instalada.',
            ], 422);
        }

        $pago = $clase::find($id);

        if (!$pago) {
            return response()->json(['success' => false, 'message' => 'El cobro no existe.'], 404);
        }

        if ($motivo = PaymentVerification::motivoBloqueo($pago->verification_status, $destino)) {
            return response()->json(['success' => false, 'message' => $motivo], 422);
        }

        $datos = $request->validate([
            // Rechazar sin decir por qué deja al operador sin saber qué
            // corregir y al cliente sin respuesta.
            'motivo' => [$destino === PaymentVerification::RECHAZADO ? 'required' : 'nullable', 'string', 'max:255'],
        ], [
            'motivo.required' => 'Indica por qué se rechaza el cobro.',
        ]);

        $pago->verification_status = $destino;
        $pago->verified_by         = auth()->id();
        $pago->verified_at         = now();
        $pago->rejection_reason    = $destino === PaymentVerification::RECHAZADO
            ? trim($datos['motivo'])
            : null;
        $pago->save();

        // Verificar el ultimo cobro pendiente puede dejar el pedido saldado: si
        // el tenant exige verificacion, es AQUI donde avanza y no al registrar.
        $avanzo = false;
        $order  = $tipo === 'order'
            ? \App\Models\Tenant\Order::find($pago->order_id)
            : optional($pago->shipment)->order;

        if ($order && $destino === PaymentVerification::VERIFICADO) {
            $avanzo = \App\Services\Tenant\OrderPaymentSync::sync($order);
        }

        return response()->json([
            'order_id' => $order?->id,
            'advanced' => $avanzo,
            'success' => true,
            'message' => $destino === PaymentVerification::VERIFICADO
                ? 'Cobro verificado.'
                : 'Cobro rechazado. El saldo del pedido vuelve a contarlo como pendiente.',
            'estado'  => $destino,
        ]);
    }
}
