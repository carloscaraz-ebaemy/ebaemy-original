<?php

namespace App\Console\Commands;

use App\Models\Tenant\Order;
use App\Models\Tenant\SalesChannel;
use App\Models\Tenant\ShippingRequest;
use App\Services\Tenant\OrderShipmentLinker;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Crea el envío de los pedidos de la tienda online (ECOM) y del marketplace
 * (MKP01) que nacieron ANTES de que sus checkouts pasaran por
 * OrderShipmentLinker::ensureFromCheckout().
 *
 * Usa solo lo que el pedido ya guardó (customer JSON y shipping_address). No
 * inventa agencia ni oficina: el panel muestra lo que falta y el cliente lo
 * completa con su enlace. Los pedidos del marketplace no guardan ubigeo (solo
 * una dirección concatenada), así que quedan sin distrito.
 *
 * Por defecto NO escribe. Idempotente. Salta anulados (5) y entregados (6).
 *
 *   php artisan shipping:backfill-checkout                 # dry-run, todos los tenants
 *   php artisan shipping:backfill-checkout --tenant=x.com  # un tenant
 *   php artisan shipping:backfill-checkout --apply         # escribe
 */
class ShippingBackfillCheckout extends Command
{
    protected $signature = 'shipping:backfill-checkout
        {--tenant= : Limitar a un fqdn concreto}
        {--apply : Escribir los envíos (sin esto es solo simulacro)}';

    protected $description = 'Crea el envío de pedidos antiguos de tienda online y marketplace a partir de lo que el pedido ya guardó';

    public function handle(): int
    {
        $apply    = (bool) $this->option('apply');
        $onlyFqdn = $this->option('tenant');

        $this->info($apply ? 'Modo APPLY — se crearán envíos.' : 'Modo DRY-RUN — no se escribe nada.');

        $tenancy  = app(Environment::class);
        $original = $tenancy->tenant();
        $seen     = [];

        foreach (Hostname::with('website')->get() as $hn) {
            if (!$hn->website || isset($seen[$hn->website->id])) {
                continue;
            }
            if ($onlyFqdn && $hn->fqdn !== $onlyFqdn) {
                continue;
            }
            $seen[$hn->website->id] = true;

            try {
                $tenancy->tenant($hn->website);
                if (!ShippingRequest::moduleInstalled() || !Schema::connection('tenant')->hasTable('sales_channels')) {
                    continue;
                }
                $s = $this->backfillCurrentTenant($apply);
                $this->line(sprintf(
                    '%s candidatos=%d con_ubigeo=%d sin_ubigeo=%d recojo=%d ya_tenian=%d %s=%d errores=%d',
                    $hn->fqdn, $s['candidates'], $s['with_ubigeo'], $s['without_ubigeo'], $s['pickup'],
                    $s['already'], $apply ? 'creados' : 'crearia', $apply ? $s['created'] : $s['candidates'], $s['errors']
                ));
            } catch (\Throwable $e) {
                $this->error("  ! {$hn->fqdn}: {$e->getMessage()}");
                Log::error('shipping:backfill-checkout tenant', ['fqdn' => $hn->fqdn, 'error' => $e->getMessage()]);
            }
        }

        $tenancy->tenant($original ?: null);

        return self::SUCCESS;
    }

    /**
     * Procesa el tenant activo.
     *
     * @return array{candidates:int,with_ubigeo:int,without_ubigeo:int,pickup:int,already:int,created:int,errors:int}
     */
    public function backfillCurrentTenant(bool $apply): array
    {
        $s = ['candidates' => 0, 'with_ubigeo' => 0, 'without_ubigeo' => 0, 'pickup' => 0,
              'already' => 0, 'created' => 0, 'errors' => 0];

        $channels = SalesChannel::whereIn('code', ['ECOM', 'MKP01'])->pluck('id', 'code');
        if ($channels->isEmpty()) {
            return $s;
        }

        $linker = app(OrderShipmentLinker::class);

        Order::whereIn('channel_id', $channels->values())
            ->whereNotIn('status_order_id', [OrderShipmentLinker::ORDER_CANCELLED, OrderShipmentLinker::ORDER_DELIVERED])
            ->orderBy('id')
            ->chunkById(200, function ($orders) use (&$s, $apply, $linker) {
                foreach ($orders as $order) {
                    try {
                        // Cualquier envío (incluso anulado) cuenta: no se resucita
                        // un envío que alguien canceló a propósito.
                        if (ShippingRequest::where('order_id', $order->id)->exists()) {
                            $s['already']++;
                            continue;
                        }

                        $c       = is_array($order->customer) ? $order->customer : (array) $order->customer;
                        $address = (string) ($c['direccion'] ?? $order->shipping_address ?? '');
                        $pickup  = mb_strtolower(trim($address)) === 'recojo en tienda';
                        $ubigeo  = preg_replace('/\D+/', '', (string) ($c['ubigeo'] ?? ''));

                        $s['candidates']++;
                        if ($pickup) {
                            $s['pickup']++;
                        }
                        strlen($ubigeo) === 6 ? $s['with_ubigeo']++ : $s['without_ubigeo']++;

                        if (!$apply) {
                            continue;
                        }

                        $shipment = $linker->ensureFromCheckout($order, [
                            'pickup'   => $pickup,
                            'ubigeo'   => $ubigeo ?: null,
                            'address'  => $address ?: ($order->shipping_address ?: null),
                            'name'     => $c['apellidos_y_nombres_o_razon_social'] ?? $c['name'] ?? null,
                            'phone'    => $c['telefono'] ?? null,
                            'document' => $c['numero_documento'] ?? $c['numero'] ?? null,
                        ]);
                        $shipment ? $s['created']++ : $s['errors']++;
                    } catch (\Throwable $e) {
                        $s['errors']++;
                        Log::warning('shipping:backfill-checkout pedido', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                    }
                }
            });

        return $s;
    }
}
