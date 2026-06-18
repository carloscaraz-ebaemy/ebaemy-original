<?php

namespace App\Services\Marketplace;

use App\CoreFacturalo\Facturalo;
use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\CoreFacturalo\Requests\Api\Transform\DocumentTransform;
use App\CoreFacturalo\Requests\Api\Validation\DocumentValidation;
use App\CoreFacturalo\Requests\Inputs\DocumentInput;
use App\Models\Tenant\Document;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\MarketplaceOrder;
use App\Models\Tenant\Series;
use App\Services\Tenant\OrderToSaleNoteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Emite la BOLETA electrónica (CPE, document_type_id=03) de un pedido de
 * marketplace (Saga), reutilizando exactamente el pipeline de la UI:
 * DocumentTransform → DocumentValidation → DocumentInput → Facturalo.
 *
 * Boleta GENÉRICA: los pedidos de Saga no traen DNI/RUC → cliente "varios"
 * (tipo doc 1, número 00000000). El total de la boleta cuadra con el total
 * cobrado por Saga (marketplace_orders.total).
 *
 * Idempotente: si el pedido ya tiene comprobante, no re-emite.
 */
class MarketplaceInvoiceService
{
    use StorageDocument;

    /** Tolerancia (S/) entre la suma de ítems y el total cobrado por Saga. */
    private const TOTAL_TOLERANCE = 0.05;

    /** PDF de la boleta en base64 (para subir a Saga). */
    public function pdfBase64(Document $document): string
    {
        return base64_encode($this->getStorage($document->filename, 'pdf'));
    }

    /**
     * Emite (o devuelve) la boleta del pedido de marketplace.
     */
    public function emitInvoice(MarketplaceOrder $mo): Document
    {
        return DB::connection('tenant')->transaction(function () use ($mo) {
            $mo = MarketplaceOrder::lockForUpdate()->findOrFail($mo->id);

            // Idempotencia 1: ya enlazado en el propio mo.
            if ($mo->document_id) {
                $existing = Document::find($mo->document_id);
                if ($existing) {
                    return $existing;
                }
            }

            // Asegura el Order interno (idempotente).
            $order = $mo->createErpOrder();

            // Idempotencia 2: el Order ya tiene comprobante (emitido por otra vía).
            if ($order->document_external_id || $order->number_document) {
                $doc = Document::where('external_id', $order->document_external_id)
                    ->orWhere('number_full', $order->number_document)
                    ->first();
                if ($doc) {
                    $mo->document_id = $doc->id;
                    $mo->save();
                    return $doc;
                }
            }

            // NV interna (trazabilidad + enlace). Idempotente.
            $saleNote = app(OrderToSaleNoteService::class)->generate($order);

            $payload = $this->buildBoletaPayload($mo, $order, $saleNote?->id);

            // Mismo pipeline que la UI (Api\DocumentController@store, sin HTTP).
            $inputs = DocumentTransform::transform($payload);
            $inputs = DocumentValidation::validation($inputs);
            $inputs = DocumentInput::set($inputs, 'api');

            $facturalo = new Facturalo();
            $facturalo->save($inputs);
            $facturalo->createXmlUnsigned();
            $service_pse_xml = $facturalo->servicePseSendXml();
            $facturalo->signXmlUnsigned($service_pse_xml['xml_signed']);
            $facturalo->updateHash($service_pse_xml['hash']);
            $facturalo->updateQr();
            $facturalo->createPdf();
            $facturalo->senderXmlSignedBill($service_pse_xml['code']);

            $document = $facturalo->getDocument();

            // Enlaces para idempotencia y para que /orders muestre el comprobante.
            $order->document_external_id = $document->external_id;
            $order->number_document = $document->number_full;
            $order->save();

            $mo->document_id = $document->id;
            $mo->save();

            Log::channel('payments')->info('Saga: boleta emitida', [
                'marketplace_order_id' => $mo->id,
                'external_order_id'    => $mo->external_order_id,
                'document_id'          => $document->id,
                'number'               => $document->number_full,
            ]);

            return $document;
        });
    }

    /**
     * Construye el payload español de una boleta (03) a partir del pedido.
     * Público para que el comando `--dry-run` pueda inspeccionarlo sin emitir.
     */
    public function buildBoletaPayload(MarketplaceOrder $mo, $order = null, $saleNoteId = null): array
    {
        $order = $order ?: $mo->createErpOrder();
        $establishment = Establishment::first();
        if (!$establishment) {
            throw new \RuntimeException('No hay establecimiento configurado para emitir.');
        }

        $series = Series::where('establishment_id', $establishment->id)
            ->where('document_type_id', '03') // Boleta
            ->first();
        if (!$series) {
            throw new \RuntimeException('No existe una serie de BOLETA (03) en el establecimiento. Créala antes de emitir.');
        }

        // ── Items desde los items de Saga (precios INCLUYEN IGV) ──
        $rawItems = is_array($mo->items_data) ? $mo->items_data : [];
        if (isset($rawItems['OrderItemId'])) {
            $rawItems = [$rawItems];
        }

        $items = [];
        $sumTotal = 0.0;
        foreach ($rawItems as $it) {
            if (!is_array($it)) {
                continue;
            }
            $desc = $it['Name'] ?? $it['ProductName'] ?? $it['descripcion'] ?? 'Producto';
            $qty  = (float) ($it['Quantity'] ?? $it['cantidad'] ?? 1) ?: 1;
            $unitPrice = (float) ($it['PaidPrice'] ?? $it['ItemPrice'] ?? $it['unit_price'] ?? 0);
            if ($unitPrice <= 0) {
                continue;
            }
            $items[] = $this->buildItemLine($desc, $qty, $unitPrice);
            $sumTotal += round($unitPrice * $qty, 2);
        }

        // Fallback: si no se pudo armar líneas válidas, una línea única por el total.
        if (empty($items)) {
            $items[] = $this->buildItemLine('Pedido Saga #' . $mo->external_order_id, 1, (float) $mo->total);
            $sumTotal = round((float) $mo->total, 2);
        }

        // Saga cobra el envío además del precio de los ítems; agrégalo como una
        // línea para que el total de la boleta cuadre con lo realmente cobrado.
        $shipping = round((float) $mo->total - $sumTotal, 2);
        if ($shipping > self::TOTAL_TOLERANCE) {
            $items[] = $this->buildItemLine('Costo de envío', 1, $shipping);
            $sumTotal = round($sumTotal + $shipping, 2);
        }

        // La boleta debe cuadrar con lo que Saga cobró.
        $diff = round($sumTotal - (float) $mo->total, 2);
        if (abs($diff) > self::TOTAL_TOLERANCE) {
            throw new \RuntimeException(sprintf(
                'El total de los ítems (S/ %.2f) no cuadra con el total de Saga (S/ %.2f), diff S/ %.2f. Revisar items_data antes de emitir.',
                $sumTotal, (float) $mo->total, $diff
            ));
        }

        // Totales de cabecera = suma de los ítems (consistencia SUNAT).
        $totalTaxed = 0.0;
        $totalIgv = 0.0;
        $totalItem = 0.0;
        foreach ($items as $line) {
            $totalTaxed += $line['total_valor_item'];
            $totalIgv   += $line['total_igv'];
            $totalItem  += $line['total_item'];
        }
        $totalTaxed = round($totalTaxed, 2);
        $totalIgv   = round($totalIgv, 2);
        $totalItem  = round($totalItem, 2);

        $customer = is_array($mo->customer_data) ? $mo->customer_data : [];
        $customerName = trim($customer['name'] ?? '') ?: 'Cliente Final';

        return [
            'serie_documento'       => $series->number,
            'numero_documento'      => '#', // autoincremento del core
            'fecha_de_emision'      => now()->format('Y-m-d'),
            'hora_de_emision'       => now()->format('H:i:s'),
            'codigo_tipo_operacion' => '0101',
            'codigo_tipo_documento' => '03', // Boleta
            'codigo_tipo_moneda'    => 'PEN',
            'fecha_de_vencimiento'  => now()->format('Y-m-d'),
            'numero_orden_de_compra' => (string) $mo->external_order_id,
            'codigo_nota_venta'     => $saleNoteId,

            'datos_del_cliente_o_receptor' => [
                'codigo_tipo_documento_identidad' => '1', // DNI
                'numero_documento'                => '00000000',
                'apellidos_y_nombres_o_razon_social' => $customerName,
                'codigo_pais'                     => 'PE',
                'ubigeo'                          => '150101',
                'direccion'                       => 'Cliente General',
                'correo_electronico'              => $customer['email'] ?? '',
                'telefono'                        => $customer['phone'] ?? '',
            ],

            'totales' => [
                'total_exportacion'             => 0,
                'total_operaciones_gravadas'    => $totalTaxed,
                'total_operaciones_inafectas'   => 0,
                'total_operaciones_exoneradas'  => 0,
                'total_operaciones_gratuitas'   => 0,
                'total_igv'                     => $totalIgv,
                'total_impuestos'               => $totalIgv,
                'total_valor'                   => $totalTaxed,
                'total_venta'                   => $totalItem,
            ],

            'items' => $items,

            'informacion_adicional' => 'Pedido Saga Falabella #' . $mo->external_order_id,
        ];
    }

    /**
     * Una línea de ítem gravado (IGV 18%) a partir de un precio que YA incluye IGV.
     */
    private function buildItemLine(string $desc, float $qty, float $unitPriceInclIgv): array
    {
        $valorUnitario = round($unitPriceInclIgv / 1.18, 2);
        $baseImponible = round($valorUnitario * $qty, 2);
        $igv           = round($baseImponible * 0.18, 2);
        $total         = round($unitPriceInclIgv * $qty, 2);

        return [
            'codigo_interno'             => '',
            'descripcion'                => mb_substr($desc, 0, 250),
            'codigo_producto_sunat'      => '51121703',
            'unidad_de_medida'           => 'NIU',
            'cantidad'                   => $qty,
            'valor_unitario'             => $valorUnitario,
            'codigo_tipo_precio'         => '01',
            'precio_unitario'            => $unitPriceInclIgv,
            'codigo_tipo_afectacion_igv' => '10', // Gravado
            'total_base_igv'             => $baseImponible,
            'porcentaje_igv'             => 18,
            'total_igv'                  => $igv,
            'total_impuestos'            => $igv,
            'total_valor_item'           => $baseImponible,
            'total_item'                 => $total,
        ];
    }
}
