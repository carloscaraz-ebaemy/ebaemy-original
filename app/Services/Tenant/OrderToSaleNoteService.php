<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Order;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\SaleNoteItem;
use App\Models\Tenant\Series;
use App\Models\Tenant\Person;
use App\Models\Tenant\Establishment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderToSaleNoteService
{
    /**
     * Auto-generate a SaleNote from a confirmed ecommerce Order.
     * Returns the SaleNote or null if generation fails.
     */
    public function generate(Order $order): ?SaleNote
    {
        // Don't generate if order already has a sale_note
        if ($order->sale_note) {
            Log::info('Order already has sale_note', ['order_id' => $order->id]);
            return $order->sale_note;
        }

        // Don't generate for cancelled orders
        if ($order->status_order_id == 5) {
            return null;
        }

        try {
            return DB::transaction(function () use ($order) {
                $establishment = Establishment::first();

                // Find or create the person (customer)
                $person = $this->resolveCustomer($order);

                // Get next sale note number
                $series = Series::where('establishment_id', $establishment->id)
                    ->where('document_type_id', '80') // 80 = Nota de Venta
                    ->first();

                if (!$series) {
                    Log::warning('No series found for sale notes', ['establishment_id' => $establishment->id]);
                    return null;
                }

                $lastNumber = SaleNote::where('series', $series->number)
                    ->max('number') ?? 0;

                // Resolve customer data from order
                $customerData = $this->getCustomerArray($order);

                // Create SaleNote
                $saleNote = new SaleNote();
                $saleNote->user_id = $order->seller_id ?? auth()->id() ?? 1;
                $saleNote->establishment_id = $establishment->id;
                $saleNote->establishment = $establishment->toArray();
                $saleNote->soap_type_id = $establishment->company->soap_type_id ?? '01';
                $saleNote->series = $series->number;
                $saleNote->number = $lastNumber + 1;
                $saleNote->date_of_issue = now()->format('Y-m-d');
                $saleNote->time_of_issue = now()->format('H:i:s');
                $saleNote->customer_id = $person?->id;
                $saleNote->customer = $customerData;
                $saleNote->currency_type_id = 'PEN';
                $saleNote->exchange_rate_sale = 1;
                $saleNote->total = $order->total;
                $saleNote->state_type_id = '01'; // valid
                $saleNote->order_id = $order->id;
                $saleNote->payment_method_type_id = $this->resolvePaymentMethod($order);
                $saleNote->total_canceled = true;
                $saleNote->paid = true;

                // Logistic fields
                $saleNote->requires_warehouse_dispatch = true;
                $saleNote->logistic_status = 'PENDIENTE';
                $saleNote->delivery_type = 'province';
                $saleNote->warehouse_id = $order->warehouse_id;

                // Shipping info from order customer data
                $saleNote->shipping_recipient = $customerData['apellidos_y_nombres_o_razon_social'] ?? null;
                $saleNote->shipping_phone = $customerData['telefono'] ?? null;
                $saleNote->shipping_address = $customerData['direccion'] ?? null;

                $saleNote->source_module = 'ecommerce';
                $saleNote->save();

                // Create SaleNoteItems
                $items = $this->getItemsArray($order);
                $totalTaxed = 0;
                $totalIgv = 0;
                $totalValue = 0;

                if (is_array($items)) {
                    foreach ($items as $orderItem) {
                        $itemId = $orderItem['item_id'] ?? $orderItem['id'] ?? null;
                        $qty = (float) ($orderItem['quantity'] ?? 1);
                        $unitPrice = (float) ($orderItem['sale_unit_price'] ?? $orderItem['unit_price'] ?? 0);
                        $total = round($qty * $unitPrice, 2);

                        // Calculate IGV (18%)
                        $subtotal = round($total / 1.18, 2);
                        $igv = round($total - $subtotal, 2);
                        $unitValue = $qty > 0 ? round($subtotal / $qty, 10) : 0;

                        $totalTaxed += $subtotal;
                        $totalIgv += $igv;
                        $totalValue += $subtotal;

                        $saleNoteItem = new SaleNoteItem();
                        $saleNoteItem->sale_note_id = $saleNote->id;
                        $saleNoteItem->item_id = $itemId;
                        $saleNoteItem->item = $orderItem;
                        $saleNoteItem->quantity = $qty;
                        $saleNoteItem->unit_value = $unitValue;
                        $saleNoteItem->unit_price = $unitPrice;
                        $saleNoteItem->total_value = $subtotal;
                        $saleNoteItem->total = $total;
                        $saleNoteItem->total_igv = $igv;
                        $saleNoteItem->total_base_igv = $subtotal;
                        $saleNoteItem->percentage_igv = 18;
                        $saleNoteItem->total_taxes = $igv;
                        $saleNoteItem->affectation_igv_type_id = '10'; // Gravado
                        $saleNoteItem->price_type_id = '01'; // Precio unitario (incluye IGV)
                        $saleNoteItem->warehouse_id = $order->warehouse_id;
                        $saleNoteItem->save();
                    }
                }

                // Update totals
                $saleNote->total_taxed = round($totalTaxed, 2);
                $saleNote->total_igv = round($totalIgv, 2);
                $saleNote->total_value = round($totalValue, 2);
                $saleNote->total_taxes = round($totalIgv, 2);
                $saleNote->save();

                // Link order to sale note
                $order->update([
                    'number_document' => $saleNote->number_full,
                ]);

                Log::channel('payments')->info('Auto-generated SaleNote from ecommerce order', [
                    'order_id' => $order->id,
                    'sale_note_id' => $saleNote->id,
                    'number_full' => $saleNote->number_full,
                    'total' => $saleNote->total,
                ]);

                return $saleNote;
            });
        } catch (\Throwable $e) {
            Log::error('Failed to auto-generate SaleNote from order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    protected function resolveCustomer(Order $order): ?Person
    {
        if ($order->person_id) {
            return Person::find($order->person_id);
        }

        $customer = $this->getCustomerArray($order);
        if (!$customer) return null;

        $number = $customer['number'] ?? $customer['numero_documento'] ?? null;
        if ($number) {
            return Person::where('number', $number)->first();
        }

        return null;
    }

    protected function resolvePaymentMethod(Order $order): string
    {
        $ref = strtolower($order->reference_payment ?? '');

        if (str_contains($ref, 'culqi') || str_contains($ref, 'card') || str_contains($ref, 'tarjeta')) {
            return '02'; // Credit/Debit card
        }
        if (str_contains($ref, 'transfer')) {
            return '03'; // Transfer
        }

        return '01'; // Cash (default)
    }

    /**
     * Get customer data as associative array from Order.
     */
    protected function getCustomerArray(Order $order): ?array
    {
        $customer = $order->customer;

        if (is_object($customer)) {
            return (array) $customer;
        }
        if (is_array($customer)) {
            return $customer;
        }
        if (is_string($customer)) {
            return json_decode($customer, true);
        }

        return null;
    }

    /**
     * Get items data as array from Order.
     */
    protected function getItemsArray(Order $order): ?array
    {
        $items = $order->items;

        if (is_object($items)) {
            return json_decode(json_encode($items), true);
        }
        if (is_array($items)) {
            return $items;
        }
        if (is_string($items)) {
            return json_decode($items, true);
        }

        return null;
    }
}
