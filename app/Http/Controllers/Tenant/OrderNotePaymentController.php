<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\ManagesRecordPayments;
use App\Models\Tenant\OrderNotePayment;
use Modules\Order\Models\OrderNote;

/**
 * Pagos de un Pedido del ERP. Toda la lógica vive en ManagesRecordPayments,
 * compartida con los pedidos del ecommerce; acá solo se declara sobre qué
 * modelo trabaja.
 */
class OrderNotePaymentController extends Controller
{
    use ManagesRecordPayments;

    protected function paymentOwner(int $id)
    {
        return OrderNote::findOrFail($id);
    }

    protected function paymentModelClass(): string
    {
        return OrderNotePayment::class;
    }

    protected function paymentForeignKey(): string
    {
        return 'order_note_id';
    }

    protected function paymentFileFolder(): string
    {
        return 'order_notes';
    }
}
