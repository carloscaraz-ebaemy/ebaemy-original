<?php

namespace App\Console\Commands;

use App\Models\Tenant\Company;
use App\Models\Tenant\Document;
use App\Models\Tenant\MarketplaceChannel;
use App\Models\Tenant\MarketplaceOrder;
use App\Services\Marketplace\MarketplaceInvoiceService;
use App\Services\Marketplace\MarketplaceOrchestrator;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;

/**
 * Prueba SEGURA de emisión + carga de boleta de UN pedido de Saga.
 *
 *   php artisan marketplace:test-invoice {orderId} --tenant=UUID --dry-run   # solo muestra el payload, NO emite
 *   php artisan marketplace:test-invoice {orderId} --tenant=UUID             # emite la boleta (1 pedido)
 *   php artisan marketplace:test-invoice {orderId} --tenant=UUID --upload    # emite Y sube a Saga
 */
class MarketplaceTestInvoice extends Command
{
    protected $signature = 'marketplace:test-invoice
                            {order : ID del marketplace_order}
                            {--tenant= : UUID del website (obligatorio)}
                            {--upload : Además sube la boleta a Saga (SetInvoicePDF)}
                            {--dry-run : No emite; solo muestra el payload y los montos}';

    protected $description = 'Prueba la emisión (y opcional carga a Saga) de la boleta de un pedido de marketplace';

    public function handle(): int
    {
        $uuid = $this->option('tenant');
        if (!$uuid) {
            $this->error('Falta --tenant=UUID.');
            return self::FAILURE;
        }

        $website = Website::where('uuid', $uuid)->first();
        if (!$website) {
            $this->error("No existe el tenant {$uuid}.");
            return self::FAILURE;
        }
        app(Environment::class)->tenant($website);

        $user = \App\Models\Tenant\User::whereNotNull('establishment_id')->first()
            ?? \App\Models\Tenant\User::first();
        if ($user) {
            auth()->setUser($user);
        }

        $mo = MarketplaceOrder::find($this->argument('order'));
        if (!$mo) {
            $this->error('No existe ese marketplace_order en el tenant.');
            return self::FAILURE;
        }

        $soap = optional(optional(Company::first()))->soap_type_id;
        $mode = $soap === '01' ? 'DEMO (beta SUNAT)' : 'PRODUCCIÓN';
        $this->line("Modo SUNAT: <comment>{$mode}</comment> (soap_type_id={$soap})");
        $this->line("Pedido Saga #{$mo->external_order_id} — estado {$mo->status} — total S/ {$mo->total}");

        $invoiceService = app(MarketplaceInvoiceService::class);

        // ── DRY-RUN: solo construir y mostrar el payload ──
        if ($this->option('dry-run')) {
            try {
                $payload = $invoiceService->buildBoletaPayload($mo);
            } catch (\Throwable $e) {
                $this->error('No se pudo construir el payload: ' . $e->getMessage());
                return self::FAILURE;
            }
            $this->info('[DRY-RUN] Payload de boleta (no se emitió nada):');
            $this->line('Serie: ' . $payload['serie_documento']);
            $this->line('Cliente: ' . $payload['datos_del_cliente_o_receptor']['apellidos_y_nombres_o_razon_social']
                . ' (DNI ' . $payload['datos_del_cliente_o_receptor']['numero_documento'] . ')');
            $this->line('Total venta: S/ ' . $payload['totales']['total_venta']
                . '  | gravado ' . $payload['totales']['total_operaciones_gravadas']
                . '  | IGV ' . $payload['totales']['total_igv']);
            $this->table(['Descripción', 'Cant', 'P.Unit', 'Total'], array_map(fn($i) => [
                mb_substr($i['descripcion'], 0, 40), $i['cantidad'], $i['precio_unitario'], $i['total_item'],
            ], $payload['items']));
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        // ── Emisión real ──
        try {
            $document = $invoiceService->emitInvoice($mo);
            $this->info('Boleta emitida: ' . $document->number_full . ' (estado SUNAT ' . $document->state_type_id . ')');
        } catch (\Throwable $e) {
            $this->error('Error al emitir: ' . $e->getMessage());
            return self::FAILURE;
        }

        // ── Subir a Saga ──
        if ($this->option('upload')) {
            $channel = MarketplaceChannel::find($mo->channel_id);
            $service = MarketplaceOrchestrator::resolveService($channel);
            if (!$service || !method_exists($service, 'setInvoicePDF')) {
                $this->error('El canal no soporta SetInvoicePDF.');
                return self::FAILURE;
            }
            try {
                $document = Document::find($mo->fresh()->document_id);
                $ids = [];
                foreach ((is_array($mo->items_data) ? $mo->items_data : []) as $it) {
                    if (is_array($it) && !empty($it['OrderItemId'])) $ids[] = $it['OrderItemId'];
                }
                $pdf = $invoiceService->pdfBase64($document);
                $service->setInvoicePDF($ids, $document->number_full, $document->date_of_issue->format('Y-m-d'), $pdf);
                $mo->update(['invoice_uploaded_at' => now(), 'invoice_upload_error' => null]);
                $this->info('Boleta subida a Saga ✓');
            } catch (\Throwable $e) {
                $this->error('Error al subir a Saga: ' . $e->getMessage());
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
