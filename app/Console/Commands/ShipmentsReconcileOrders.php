<?php

namespace App\Console\Commands;

use App\Models\Tenant\Order;
use App\Models\Tenant\ShippingRequest;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Conciliación de envíos huérfanos (unificación Pedidos ↔ Envíos).
 *
 * Antes de la unificación, `shipping_requests` era un módulo autónomo: el
 * cliente se registraba solo y el envío nacía SIN pedido. Esos registros son
 * válidos y no se tocan, pero mientras existan no se puede poner
 * `order_id NOT NULL` ni un UNIQUE en la columna.
 *
 * Este comando responde las dos preguntas que bloquean ese paso:
 *   1. ¿Cuántos envíos no tienen pedido, y desde cuándo?
 *   2. ¿Hay pedidos con MÁS DE UN envío vigente (duplicados)?
 *
 * Es de solo lectura salvo que se pase --link, y aun así solo vincula donde la
 * correspondencia es inequívoca. Nunca borra nada.
 *
 * Uso:
 *   php artisan shipments:reconcile                 # reporte de todos los tenants
 *   php artisan shipments:reconcile --tenant=x.com  # un solo tenant
 *   php artisan shipments:reconcile --link          # vincula las coincidencias seguras
 */
class ShipmentsReconcileOrders extends Command
{
    protected $signature = 'shipments:reconcile
        {--tenant= : Limitar a un fqdn concreto}
        {--link : Vincular los envíos cuya correspondencia con un pedido es inequívoca}
        {--days=90 : Ventana hacia atrás, en días, para buscar el pedido candidato}';

    protected $description = 'Reporta envíos sin pedido y pedidos con envíos duplicados (unificación Pedidos ↔ Envíos)';

    public function handle(): int
    {
        $link     = (bool) $this->option('link');
        $onlyFqdn = $this->option('tenant');
        $days     = max(1, (int) $this->option('days'));

        $this->info($link
            ? 'Modo LINK — se vincularán solo las coincidencias inequívocas.'
            : 'Modo REPORTE — no se modifica nada.');
        $this->line('');

        $tenancy   = app(Environment::class);
        $original  = $tenancy->tenant();
        $hostnames = Hostname::with('website')->get();

        $totals = ['orphans' => 0, 'linked' => 0, 'ambiguous' => 0, 'duplicates' => 0, 'dangling' => 0];

        // Un mismo tenant puede tener VARIOS hostnames apuntando a la misma
        // base (dominio + localhost, o un alias). Sin esto cada envio se
        // contaba una vez por hostname y los totales salian inflados, que es
        // justo el numero con el que se decide si ya se puede poner la FK.
        $seenWebsites = [];

        foreach ($hostnames as $hn) {
            if (!$hn->website) {
                continue;
            }
            if ($onlyFqdn && $hn->fqdn !== $onlyFqdn) {
                continue;
            }
            if (isset($seenWebsites[$hn->website->id])) {
                continue;
            }
            $seenWebsites[$hn->website->id] = true;

            try {
                $tenancy->tenant($hn->website);

                if (!Schema::connection('tenant')->hasTable('shipping_requests')) {
                    continue;   // el tenant no tiene el módulo de Envíos
                }

                $this->line("<fg=cyan>{$hn->fqdn}</>");
                $stats = $this->reconcileTenant($link, $days);

                foreach ($totals as $key => $_) {
                    $totals[$key] += $stats[$key];
                }
            } catch (\Throwable $e) {
                $this->error("  ! {$hn->fqdn}: {$e->getMessage()}");
            }
        }

        $tenancy->tenant($original);

        $this->line('');
        $this->info('── Total ──────────────────────────────────────────');
        $this->line("  Envíos sin pedido      : {$totals['orphans']}");
        $this->line("  Vinculados             : {$totals['linked']}");
        $this->line("  Ambiguos (revisar)     : {$totals['ambiguous']}");
        $this->line("  Pedidos con duplicados : {$totals['duplicates']}");
        $this->line("  Pedido inexistente     : {$totals['dangling']}");
        $this->line('');

        if ($totals['orphans'] - $totals['linked'] > 0) {
            $this->comment('Quedan envíos sin pedido: NO se puede aplicar order_id NOT NULL todavía.');
        }
        if ($totals['dangling'] > 0) {
            $this->comment('Hay envíos apuntando a pedidos inexistentes: NO se puede aplicar la FK todavía.');
        }
        if ($totals['duplicates'] > 0) {
            $this->comment('Hay pedidos con más de un envío vigente: NO se puede aplicar UNIQUE(order_id) todavía.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{orphans:int, linked:int, ambiguous:int, duplicates:int, dangling:int}
     */
    private function reconcileTenant(bool $link, int $days): array
    {
        $stats = ['orphans' => 0, 'linked' => 0, 'ambiguous' => 0, 'duplicates' => 0, 'dangling' => 0];

        // ── 1. Pedidos con más de un envío vigente ──────────────────────
        // Bloquean el UNIQUE. Se listan para que alguien decida cuál vale;
        // el comando NO elige por su cuenta: anular el envío equivocado
        // significaría perder su bitácora y sus rótulos.
        $duplicates = ShippingRequest::query()
            ->whereNotNull('order_id')
            ->whereNull('cancelled_at')
            ->selectRaw('order_id, COUNT(*) as total')
            ->groupBy('order_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $stats['duplicates'] = $duplicates->count();

        foreach ($duplicates as $dup) {
            $codes = ShippingRequest::where('order_id', $dup->order_id)
                ->whereNull('cancelled_at')
                ->pluck('shipment_code', 'id')
                ->map(fn($c, $id) => ($c ?: "#$id"))
                ->implode(', ');
            $this->line("  <fg=red>x</> Pedido #{$dup->order_id} tiene {$dup->total} envíos vigentes: {$codes}");
        }

        // ── 2. Envíos que apuntan a un pedido inexistente ───────────────
        // Tan bloqueantes para la FK como los huérfanos, y mucho más difíciles
        // de ver: la columna trae un número, así que a simple vista el envío
        // parece correctamente vinculado. Aparecen por pedidos borrados o por
        // datos de prueba cargados a mano.
        $dangling = ShippingRequest::query()
            ->whereNotNull('order_id')
            ->whereDoesntHave('order')
            ->orderBy('id')
            ->get();

        $stats['dangling'] = $dangling->count();

        foreach ($dangling as $shipment) {
            $this->line("  <fg=red>x</> {$this->label($shipment)} apunta al pedido #{$shipment->order_id}, que no existe");
        }

        // ── 3. Envíos sin pedido ────────────────────────────────────────
        $orphans = ShippingRequest::orphan()->orderBy('id')->get();
        $stats['orphans'] = $orphans->count();

        if ($orphans->isEmpty()) {
            if ($dangling->isEmpty() && $duplicates->isEmpty()) {
                $this->line('  <fg=green>=</> Sin envíos huérfanos.');
            }
            return $stats;
        }

        foreach ($orphans as $shipment) {
            $candidates = $this->candidateOrders($shipment, $days);

            if ($candidates->count() === 1) {
                $order = $candidates->first();
                if ($link) {
                    $shipment->forceFill(['order_id' => $order->id])->save();
                    $stats['linked']++;
                    $this->line("  <fg=green>+</> {$this->label($shipment)} → pedido #{$order->id}");
                } else {
                    $this->line("  <fg=yellow>~</> {$this->label($shipment)} → se vincularía al pedido #{$order->id}");
                }
                continue;
            }

            $stats['ambiguous']++;
            $motivo = $candidates->isEmpty()
                ? 'sin pedido candidato'
                : "{$candidates->count()} pedidos candidatos";
            $this->line("  <fg=gray>?</> {$this->label($shipment)} — {$motivo} (revisión manual)");
        }

        return $stats;
    }

    /**
     * Pedidos que podrían corresponder a este envío.
     *
     * Criterio deliberadamente ESTRECHO: mismo documento o mismo teléfono, y
     * dentro de la ventana temporal. Vincular de más es peor que no vincular:
     * un envío colgado del pedido equivocado manda el paquete a otra persona.
     */
    private function candidateOrders(ShippingRequest $shipment, int $days)
    {
        $doc   = preg_replace('/\D+/', '', (string) $shipment->dni);
        $phone = preg_replace('/\D+/', '', (string) $shipment->phone);

        if ($doc === '' && $phone === '') {
            return collect();
        }

        $from = $shipment->created_at
            ? $shipment->created_at->copy()->subDays($days)
            : now()->subDays($days);
        $to = $shipment->created_at ?: now();

        return Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('status_order_id', '!=', 5)   // los cancelados no reciben envíos
            // El envío ya vinculado a otro pedido no vuelve a estar disponible.
            ->whereDoesntHave('shipments', fn($s) => $s->whereNull('cancelled_at'))
            ->where(function ($w) use ($doc, $phone) {
                if ($doc !== '')   $w->orWhere('customer', 'like', "%{$doc}%");
                if ($phone !== '') $w->orWhere('customer', 'like', "%{$phone}%");
            })
            ->limit(5)
            ->get();
    }

    private function label(ShippingRequest $shipment): string
    {
        $code = $shipment->shipment_code ?: "#{$shipment->id}";
        $date = optional($shipment->created_at)->format('Y-m-d') ?: 's/f';

        return "{$code} ({$date}, {$shipment->full_name})";
    }
}
