<?php

namespace App\Console\Commands;

use App\Models\Tenant\MarketplaceOrder;
use App\Services\Marketplace\ExternalPaymentSeeder;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;

/**
 * Siembra el cobro del canal en los pedidos de Saga que YA estaban importados.
 *
 * El arreglo del importador solo actúa sobre los pedidos que entran a partir de
 * ahora. Los que ya estaban siguen sin cobro registrado: su saldo es el total,
 * el panel los muestra a deber una venta cobrada y admiten un cobro manual
 * encima. Esto los pone al día.
 *
 * ── Escribe dinero: por eso NO hace nada salvo que se lo pidan ────────────
 *
 * Por defecto es una SIMULACIÓN. Hay que pasar `--apply` explícitamente para que
 * escriba. Es al revés que el resto de comandos del proyecto, y a propósito: el
 * daño de un `--dry-run` olvidado aquí no es una importación a medias, son
 * cobros inventados en la contabilidad de un tenant.
 *
 * Los pedidos que ya tienen un cobro cargado a mano NO se tocan: se listan para
 * que una persona decida. Cuál de los dos es el bueno no se puede adivinar.
 *
 *   php artisan marketplace:falabella-backfill-payments --tenant=UUID
 *   php artisan marketplace:falabella-backfill-payments --tenant=UUID --apply
 */
class FalabellaBackfillPayments extends Command
{
    protected $signature = 'marketplace:falabella-backfill-payments
                            {--tenant= : UUID del website (obligatorio)}
                            {--apply : Escribe de verdad. Sin esto solo simula}
                            {--limit=0 : Máximo de pedidos a tratar (0 = todos)}';

    protected $description = 'Siembra el cobro del canal en los pedidos de Saga ya importados (simula salvo --apply)';

    public function handle(): int
    {
        $uuid = $this->option('tenant');

        if (!$uuid) {
            $this->error('Falta --tenant=UUID.');

            return self::FAILURE;
        }

        $website = Website::where('uuid', $uuid)->first();

        if (!$website) {
            $this->error("No existe el tenant con uuid {$uuid}.");

            return self::FAILURE;
        }

        app(Environment::class)->tenant($website);

        if (!MarketplaceOrder::moduleInstalled()) {
            $this->error("El tenant {$uuid} no tiene la tabla marketplace_orders.");

            return self::FAILURE;
        }

        $aplicar = (bool) $this->option('apply');
        $limite = max(0, (int) $this->option('limit'));

        $this->info(($aplicar ? '' : '[SIMULACIÓN] ') . "Cobros del canal — tenant {$uuid}");

        if (!$aplicar) {
            $this->line('Nada se va a escribir. Repite con --apply cuando el resultado te cuadre.');
        }

        $query = MarketplaceOrder::whereNotNull('order_id')->with('order')->orderBy('id');

        if ($limite > 0) {
            $query->limit($limite);
        }

        $pedidos = $query->get();

        $seeder = new ExternalPaymentSeeder();
        $sembrados = 0;
        $yaEstaban = 0;
        $importe = 0.0;
        $conflictos = [];
        $saltados = [];

        foreach ($pedidos as $mpOrder) {
            $order = $mpOrder->order;

            if (!$order) {
                $saltados[] = [$mpOrder->external_order_id, 'El pedido enlazado ya no existe.'];
                continue;
            }

            $motivo = null;
            $pago = $seeder->seed($order, $mpOrder, $motivo, !$aplicar);

            if (!$pago) {
                // El conflicto que importa —ya hay un cobro manual— va aparte de
                // los saltos inocuos (pedido sin importe, sin referencia).
                if (str_contains((string) $motivo, 'a mano')) {
                    $conflictos[] = [$mpOrder->external_order_id, $motivo];
                } else {
                    $saltados[] = [$mpOrder->external_order_id, $motivo];
                }

                continue;
            }

            // Vale para los dos modos: en simulación el cobro nuevo viene sin
            // guardar (`exists` false) y el que ya estaba viene de la base.
            if ($pago->exists && !$pago->wasRecentlyCreated) {
                $yaEstaban++;
                continue;
            }

            $sembrados++;
            $importe += (float) $pago->payment;
        }

        $this->newLine();
        $this->table(
            ['Pedidos', $aplicar ? 'Sembrados' : 'Se sembrarían', 'Ya tenían', 'Conflictos', 'Saltados', 'Importe'],
            [[
                $pedidos->count(),
                $sembrados,
                $yaEstaban,
                count($conflictos),
                count($saltados),
                'S/ ' . number_format($importe, 2),
            ]]
        );

        if ($conflictos) {
            $this->newLine();
            $this->error('REVISAR A MANO — estos pedidos ya tienen un cobro cargado por una persona.');
            $this->line('Sembrar el del canal encima duplicaría el importe, así que NO se tocaron.');
            $this->table(
                ['Pedido en el canal', 'Motivo'],
                array_map(fn($c) => [$c[0], substr((string) $c[1], 0, 80)], array_slice($conflictos, 0, 50))
            );
        }

        if ($saltados) {
            $this->newLine();
            $this->line('Saltados (sin importe, sin referencia, o enlace roto):');
            $this->table(
                ['Pedido en el canal', 'Motivo'],
                array_map(fn($c) => [$c[0], substr((string) $c[1], 0, 80)], array_slice($saltados, 0, 20))
            );
        }

        if (!$aplicar && $sembrados > 0) {
            $this->newLine();
            $this->warn("Repite con --apply para escribir esos {$sembrados} cobros.");
        }

        return self::SUCCESS;
    }
}
