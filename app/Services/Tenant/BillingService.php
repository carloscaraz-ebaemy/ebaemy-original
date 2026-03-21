<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Configuration;
use App\Models\Tenant\Document;
use App\Models\Tenant\LogisticOrder;
use App\Models\Tenant\LogisticShippingGuide;
use App\Models\Tenant\Person;
use App\Models\Tenant\Series;
use Illuminate\Support\Facades\Storage;

/**
 * BillingService — Integración entre el sistema logístico y la facturación SUNAT existente.
 *
 * Los productos son FACTURABLES: cada venta (tienda o provincia) genera
 * un comprobante electrónico (Boleta 03 o Factura 01) que se envía al
 * sistema SUNAT a través del motor Facturalo existente (CoreFacturalo/Facturalo.php).
 *
 * Esta capa actúa como puente: traduce una LogisticOrder al formato
 * esperado por el CoreFacturalo.
 */
class BillingService
{
    /**
     * Genera el comprobante electrónico (Boleta/Factura) para una orden logística.
     * Usa el motor CoreFacturalo existente del proyecto.
     *
     * @param LogisticOrder $order
     * @param array $data { document_type_id, series (opcional), currency_type_id }
     */
    public function generateDocument(LogisticOrder $order, array $data): Document
    {
        /** @var Configuration $config */
        $config = Configuration::first();
        $documentTypeId = $data['document_type_id'] ?? '03'; // 03=Boleta por defecto

        // Obtener la serie activa para el tipo de comprobante
        $series = $this->getActiveSeries($documentTypeId, $data['series'] ?? null);

        // Armar el payload para el motor Facturalo
        $payload = $this->buildFacturaloPayload($order, $documentTypeId, $series, $config);

        // Usar el CoreFacturalo para crear y enviar el documento
        $facturalo = app(\App\CoreFacturalo\Facturalo::class);
        $facturalo->setData($payload);
        $facturalo->save();

        /** @var Document $document */
        $document = $facturalo->getDocument();

        // Vincular el documento a la orden logística
        $order->document_id = $document->id;
        $order->save();

        return $document;
    }

    /**
     * Genera el PDF de la guía de remisión para despacho a provincia.
     * Almacena en storage del tenant para aislamiento.
     *
     * @return string|null Ruta relativa en storage
     */
    public function generateShippingGuidePdf(LogisticOrder $order, LogisticShippingGuide $guide): ?string
    {
        try {
            $tenantUuid = app(\Hyn\Tenancy\Environment::class)->tenant()?->uuid ?? 'system';
            $directory  = "tenants/{$tenantUuid}/shipping_guides";
            $filename   = "guide_{$order->id}_" . now()->format('Ymd_His') . '.pdf';
            $path       = "{$directory}/{$filename}";

            // Generar PDF con la vista existente del módulo Dispatch
            $html = view('logistic.shipping_guide_pdf', [
                'order' => $order->load('items.item', 'customer', 'warehouse'),
                'guide' => $guide,
            ])->render();

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4');

            Storage::put($path, $pdf->output());

            return $path;

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('[BillingService] Error generando PDF guía', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ─── Helpers privados ────────────────────────────────────────────────────

    /**
     * Traduce LogisticOrder al array que espera CoreFacturalo/Facturalo.
     * Usa los mismos campos que DocumentController utiliza en el sistema existente.
     */
    private function buildFacturaloPayload(
        LogisticOrder $order,
        string        $documentTypeId,
        Series        $series,
        Configuration $config
    ): array {
        /** @var Person|null $customer */
        $customer = $order->customer;

        $items = $order->items->map(function ($orderItem) {
            return [
                'item_id'                 => $orderItem->item_id,
                'item'                    => [
                    'description'         => $orderItem->description,
                    'unit_type_id'        => $orderItem->unit_type_id,
                ],
                'quantity'                => $orderItem->quantity,
                'unit_price'              => $orderItem->unit_price,
                'unit_price_with_igv'     => $orderItem->unit_price_with_igv,
                'price_type_id'           => '01',
                'affectation_igv_type_id' => $orderItem->affectation_igv_type_id,
                'total_base_igv'          => $orderItem->total_base_igv,
                'total_igv'               => $orderItem->total_igv,
                'total'                   => $orderItem->total,
                'has_igv'                 => $orderItem->affectation_igv_type_id === '10',
            ];
        })->toArray();

        return [
            'document_type_id'     => $documentTypeId,
            'series_id'            => $series->id,
            'customer'             => [
                'identity_document_type_id' => $customer?->identity_document_type_id ?? '1',
                'number'                    => $customer?->number ?? '00000000',
                'name'                      => $customer?->name ?? $order->recipient_name ?? 'Cliente Final',
                'address'                   => $customer?->address ?? $order->destination_address,
            ],
            'currency_type_id'     => $order->currency_type_id,
            'exchange_rate_sale'   => 1,
            'date_of_issue'        => now()->toDateString(),
            'time_of_issue'        => now()->format('H:i:s'),
            'items'                => $items,
            'total_exportation'    => 0,
            'total_free'           => 0,
            'total_taxed'          => $order->subtotal,
            'total_unaffected'     => 0,
            'total_exonerated'     => 0,
            'total_igv'            => $order->igv,
            'total_value'          => $order->subtotal,
            'total'                => $order->total,
            'charges'              => [],
            'discounts'            => [],
            'perception'           => null,
            'guides'               => [],
            'establishment_id'     => auth()->user()?->establishment_id,
            'soap_type_id'         => $config->soap_type_id ?? '02',
            // Metadata del origen logístico
            'logistic_order_id'    => $order->id,
            'delivery_type'        => $order->delivery_type->value,
        ];
    }

    private function getActiveSeries(string $documentTypeId, ?string $preferredSeries): Series
    {
        $query = Series::where('document_type_id', $documentTypeId)
                       ->where('establishment_id', auth()->user()?->establishment_id);

        if ($preferredSeries) {
            $query->where('number', $preferredSeries);
        }

        $series = $query->first();

        if (!$series) {
            throw new \RuntimeException(
                "No se encontró serie activa para el tipo de documento {$documentTypeId}"
            );
        }

        return $series;
    }
}
