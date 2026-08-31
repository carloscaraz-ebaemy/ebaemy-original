<?php

namespace App\Http\Controllers\Tenant\Concerns;

use App\Models\Tenant\PaymentMethodType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Traits\FilePaymentTrait;
use Modules\Finance\Traits\FinanceTrait;

/**
 * ManagesRecordPayments — el panel de pagos, una sola vez.
 *
 * Nota de Venta ya tenía este flujo completo (fecha, método, destino,
 * referencia, archivo, monto, y el saldo arriba). Los dos tipos de Pedido
 * tenían apenas una parte. En vez de copiar el controlador dos veces más, la
 * lógica vive acá y cada controlador declara sobre qué modelo trabaja.
 *
 * Quien lo use debe implementar paymentOwner() y paymentModelClass(), y el
 * modelo dueño debe usar App\Traits\HasAmountDuePayments.
 */
trait ManagesRecordPayments
{
    use FinanceTrait, FilePaymentTrait;

    /** Registro dueño de los pagos (Order u OrderNote). */
    abstract protected function paymentOwner(int $id);

    /** Clase del pago (OrderPayment u OrderNotePayment). */
    abstract protected function paymentModelClass(): string;

    /** Nombre de la FK hacia el dueño: order_id / order_note_id. */
    abstract protected function paymentForeignKey(): string;

    /** Carpeta donde se guardan los archivos adjuntos de estos pagos. */
    abstract protected function paymentFileFolder(): string;

    /**
     * Catálogos del formulario. Los destinos salen de FinanceTrait, así que
     * caja y cuentas bancarias son exactamente las mismas que en nota de venta.
     */
    public function tables()
    {
        return [
            'payment_method_types' => PaymentMethodType::all(),
            'payment_destinations' => $this->getPaymentDestinations(),
        ];
    }

    /**
     * Cabecera del panel: cuánto hay que cobrar, cuánto se cobró y cuánto
     * falta. Mismas claves que devuelve SaleNotePaymentController::document
     * para que el componente Vue sea uno solo.
     */
    public function summary($id)
    {
        return $this->paymentOwner((int) $id)->getPaymentSummary();
    }

    /** Pagos ya registrados, listos para la grilla. */
    public function records($id)
    {
        $class = $this->paymentModelClass();

        $records = $class::where($this->paymentForeignKey(), (int) $id)
            ->with(['payment_method_type', 'payment_file'])
            ->orderBy('date_of_payment')
            ->orderBy('id')
            ->get()
            ->map(function ($row) {
                return [
                    'id'                            => $row->id,
                    'code'                          => 'PAGO-' . $row->id,
                    'date_of_payment'               => optional($row->date_of_payment)->format('Y-m-d'),
                    'payment_method_type_id'        => $row->payment_method_type_id,
                    'payment_method_type_description' => optional($row->payment_method_type)->description,
                    'payment_destination_id'        => $row->payment_destination_id,
                    'destination_description'       => $this->describeDestination($row->payment_destination_id),
                    'reference'                     => $row->reference,
                    'filename'                      => optional($row->payment_file)->filename,
                    'payment'                       => (float) $row->payment,
                ];
            });

        return ['data' => $records];
    }

    /**
     * Registra un pago. Se valida contra el saldo REAL del registro, no contra
     * lo que mande el front: un formulario manipulado no puede sobrepagar.
     */
    public function store(Request $request)
    {
        $fk = $this->paymentForeignKey();

        $request->validate([
            $fk                      => 'required|integer',
            'date_of_payment'        => 'required|date',
            'payment_method_type_id' => 'required',
            'payment'                => 'required|numeric|min:0.01',
            'reference'              => 'nullable|string|max:191',
        ], [
            'payment.min' => 'El monto del pago debe ser mayor a cero.',
        ]);

        $owner = $this->paymentOwner((int) $request->input($fk));
        $id    = $request->input('id');
        $class = $this->paymentModelClass();

        // Saldo disponible: si es una edición, el propio pago no cuenta.
        $saldo = $owner->total_difference;
        if ($id) {
            $saldo += (float) optional($class::find($id))->payment;
        }

        if (round((float) $request->input('payment'), 2) > round($saldo, 2) + 0.001) {
            return [
                'success' => false,
                'message' => 'El pago (' . number_format((float) $request->input('payment'), 2) . ') supera el saldo pendiente (' . number_format($saldo, 2) . ').',
            ];
        }

        $record = null;

        DB::connection('tenant')->transaction(function () use ($id, $request, $class, &$record) {
            $record = $class::firstOrNew(['id' => $id]);
            $record->fill($request->all());
            $record->save();

            // Al editar, el asiento y el archivo viejos ya no aplican.
            if ($id) {
                $record->global_payment()->delete();
            }

            $this->createGlobalPayment($record, $request->all());
            $this->saveFiles($record, $request, $this->paymentFileFolder());
        });

        return [
            'success' => true,
            'message' => $id ? 'Pago editado con éxito' : 'Pago registrado con éxito',
            'summary' => $this->paymentOwner((int) $request->input($fk))->getPaymentSummary(),
        ];
    }

    /** Borra el pago junto con su asiento en Finanzas. */
    public function destroy($id)
    {
        $class  = $this->paymentModelClass();
        $record = $class::findOrFail($id);
        $owner  = $this->paymentOwner((int) $record->{$this->paymentForeignKey()});

        DB::connection('tenant')->transaction(function () use ($record) {
            $record->global_payment()->delete();
            $record->delete();
        });

        return [
            'success' => true,
            'message' => 'Pago eliminado con éxito',
            'summary' => $owner->fresh()->getPaymentSummary(),
        ];
    }

    /**
     * Guarda el monto a cobrar escrito a mano. Vaciarlo vuelve a cobrar contra
     * el total de los productos.
     */
    public function storeAmountDue(Request $request, $id)
    {
        $request->validate([
            'amount_due' => 'nullable|numeric|min:0',
        ], [
            'amount_due.min' => 'El monto no puede ser negativo.',
        ]);

        $owner = $this->paymentOwner((int) $id);
        $raw   = $request->input('amount_due');

        $nuevo = ($raw === null || $raw === '') ? null : round((float) $raw, 2);

        // No se puede fijar un monto menor a lo ya cobrado: dejaria el saldo
        // en negativo y el pedido en un estado que nadie sabe leer.
        if ($nuevo !== null && $nuevo < $owner->total_paid) {
            return [
                'success' => false,
                'message' => 'El monto a cobrar (' . number_format($nuevo, 2) . ') no puede ser menor a lo ya pagado (' . number_format($owner->total_paid, 2) . ').',
            ];
        }

        $owner->amount_due = $nuevo;
        $owner->save();

        return [
            'success' => true,
            'message' => 'Monto a cobrar actualizado',
            'summary' => $owner->fresh()->getPaymentSummary(),
        ];
    }

    /** "Caja" o el nombre de la cuenta bancaria, para mostrar en la grilla. */
    private function describeDestination($destinationId): ?string
    {
        if ($destinationId === null || $destinationId === '') return null;
        if ($destinationId === 'cash') return 'Caja';

        static $cache = null;
        if ($cache === null) {
            $cache = collect($this->getPaymentDestinations())->keyBy('id');
        }

        return optional($cache->get($destinationId))['description'] ?? null;
    }
}
