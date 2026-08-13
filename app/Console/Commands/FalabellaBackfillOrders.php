<?php

namespace App\Console\Commands;

use App\Models\Tenant\MarketplaceChannel;
use App\Models\Tenant\MarketplaceOrder;
use App\Services\Marketplace\FalabellaService;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;

/**
 * Completa los pedidos de Saga ya importados y reconcilia comprobantes.
 *
 * Dos problemas que resuelve de una pasada:
 *
 *  1. Los pedidos viejos se guardaron solo con nombre/email/telefono: el DNI
 *     venia en la respuesta de Saga y se descartaba, asi que no se podia
 *     emitir la boleta identificando al cliente.
 *
 *  2. No se sabia cuales ya tienen comprobante cargado EN SAGA (emitidos por
 *     fuera) y cuales faltan de verdad.
 *
 * Por defecto NO escribe: hay que pasar --apply.
 */
class FalabellaBackfillOrders extends Command
{
    protected $signature = 'marketplace:falabella-backfill-orders
                            {--tenant= : UUID del website (por defecto, todos los que tengan canal Saga)}
                            {--apply : Guarda los cambios. Sin esto solo reporta.}
                            {--limit=0 : Maximo de pedidos a revisar (0 = todos)}
                            {--skip-invoices : No consultar el comprobante en Saga (mas rapido)}';

    protected $description = 'Rellena el documento del comprador y reconcilia que pedidos de Saga ya tienen comprobante';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('apply');
        $limite  = (int) $this->option('limit');
        $sinFact = (bool) $this->option('skip-invoices');

        if (!$aplicar) {
            $this->warn('Modo REPORTE — no se guarda nada. Usa --apply para escribir.');
        }

        $websites = Website::query()
            ->when($this->option('tenant'), fn ($q) => $q->where('uuid', $this->option('tenant')))
            ->get();

        foreach ($websites as $website) {
            app(Environment::class)->tenant($website);

            $channel = MarketplaceChannel::where('platform', 'falabella')
                                         ->where('status', 'active')->first();
            if (!$channel) {
                continue;
            }

            $this->info(PHP_EOL . '→ ' . $website->uuid);
            $this->procesarTenant($channel, $aplicar, $limite, $sinFact);
        }

        return self::SUCCESS;
    }

    private function procesarTenant(MarketplaceChannel $channel, bool $aplicar, int $limite, bool $sinFact): void
    {
        $servicio = new FalabellaService($channel);

        $pedidos = MarketplaceOrder::where('channel_id', $channel->id)
            ->orderByDesc('id')
            ->when($limite > 0, fn ($q) => $q->limit($limite))
            ->get();

        // Se sondea una vez: ¿esta cuenta devuelve datos de comprobante?
        $soportaFacturas = false;
        if (!$sinFact && $pedidos->isNotEmpty()) {
            $soportaFacturas = $servicio->supportsInvoiceLookup($pedidos->first()->external_order_id);
            if (!$soportaFacturas) {
                $this->warn('   Esta cuenta de Saga NO expone comprobantes en la API '
                          . '(no vienen InvoiceNumber ni InvoiceDocumentLink). '
                          . 'No se puede saber por aqui cuales ya estan facturados; '
                          . 'se omite esa comparacion en vez de reportar un numero falso.');
            }
        }

        $conDoc = 0; $sinDoc = 0; $completados = 0;
        $yaFacturados = 0; $porFacturar = 0; $errores = 0;
        $ejemplos = [];

        foreach ($pedidos as $pedido) {
            $datos = is_array($pedido->customer_data)
                ? $pedido->customer_data
                : (json_decode((string) $pedido->customer_data, true) ?: []);

            // ── 1. Documento del comprador ──────────────────────────────
            if (empty($datos['document'])) {
                try {
                    $crudo = $servicio->fetchRawOrder($pedido->external_order_id);
                    if ($crudo) {
                        $nuevos = $servicio->buildCustomerData($crudo);
                        if (!empty($nuevos['document'])) {
                            $completados++;
                            if (count($ejemplos) < 3) {
                                $ejemplos[] = $pedido->external_order_id . ' → ' . $nuevos['document']
                                            . ' (' . $nuevos['name'] . ')';
                            }
                            if ($aplicar) {
                                $pedido->update(['customer_data' => $nuevos]);
                            }
                        }
                        $datos = $nuevos;
                    }
                } catch (\Throwable $e) {
                    $errores++;
                }
            }

            empty($datos['document']) ? $sinDoc++ : $conDoc++;

            // ── 2. ¿Ya tiene comprobante? ───────────────────────────────
            // Solo si la cuenta expone el dato: si no, comparar da "ninguno
            // facturado" siempre y seria un numero inventado.
            if ($sinFact || $soportaFacturas === false) {
                continue;
            }

            // Emitido desde EBAEMY, o ya marcado antes: no hay nada que mirar.
            if ($pedido->document_id || $pedido->invoice_uploaded_at) {
                $yaFacturados++;
                continue;
            }

            try {
                $numero = $servicio->getOrderInvoiceNumber($pedido->external_order_id);
            } catch (\Throwable $e) {
                $errores++;
                continue;
            }

            if ($numero) {
                $yaFacturados++;
                if ($aplicar) {
                    $pedido->update([
                        'invoice_uploaded_at'  => now(),
                        'invoice_upload_error' => 'Comprobante cargado en Saga: ' . $numero,
                    ]);
                }
            } else {
                $porFacturar++;
            }
        }

        $estadoFact = $soportaFacturas
            ? [$yaFacturados, $porFacturar]
            : ['no verificable', 'no verificable'];

        $this->table(
            ['Pedidos', 'Con documento', 'Sin documento', 'Completados ahora', 'Ya facturados', 'Faltan facturar', 'Errores'],
            [[$pedidos->count(), $conDoc, $sinDoc, $completados, $estadoFact[0], $estadoFact[1], $errores]]
        );

        foreach ($ejemplos as $e) {
            $this->line('   ejemplo: ' . $e);
        }
    }
}
