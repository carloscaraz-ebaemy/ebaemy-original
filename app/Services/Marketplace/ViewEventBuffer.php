<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Las vistas y los clicks del marketplace: se apuntan en el momento, se suman
 * una vez por minuto.
 *
 * `record()` corre DENTRO de la peticion del visitante, asi que hace lo minimo
 * posible: un INSERT en una tabla que solo crece. `flush()` corre desde el
 * cron y es el unico que toca los contadores de verdad.
 *
 * Ver la migracion de `marketplace_view_events` para el porque del diseno.
 */
class ViewEventBuffer
{
    /** Las unicas metricas validas — son nombres de columna, no entran sin filtrar. */
    public const METRICS = ['views', 'clicks'];

    /** Cuantos eventos agrega una pasada del cron, para no abrir una transaccion larga. */
    public const FLUSH_LIMIT = 50000;

    /**
     * Apunta una vista o un click. Nunca lanza: perder una metrica es
     * aceptable, tumbar la ficha del producto por un fallo de escritura no.
     */
    public function record(int $listingId, ?int $hostnameId, string $metric): void
    {
        if (!in_array($metric, self::METRICS, true)) return;

        try {
            DB::connection('system')->table('marketplace_view_events')->insert([
                'listing_id'  => $listingId,
                'hostname_id' => $hostnameId,
                'metric'      => $metric,
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ViewEventBuffer::record] ' . $e->getMessage());
        }
    }

    /**
     * Agrupa lo acumulado y lo aplica: un UPDATE por producto/metrica y un
     * upsert por producto/dia, en vez de uno por visita.
     *
     * Se trabaja contra un `id` techo fijado al principio para que lo que
     * entre durante la pasada quede para la siguiente — asi el DELETE no se
     * lleva por delante eventos que no se han sumado.
     *
     * @return array{eventos:int, productos:int, dias:int}
     */
    public function flush(int $limit = self::FLUSH_LIMIT): array
    {
        $vacio = ['eventos' => 0, 'productos' => 0, 'dias' => 0];

        // Techo de la pasada: el id del evento numero $limit, o el ultimo si
        // hay menos. Sin esto una acumulacion grande abriria una transaccion
        // larguisima y bloquearia la tabla entera.
        $maxId = (int) (DB::connection('system')->table('marketplace_view_events')
            ->orderBy('id')->skip($limit - 1)->take(1)->value('id')
            ?: DB::connection('system')->table('marketplace_view_events')->max('id'));

        if ($maxId <= 0) return $vacio;

        $agregado = DB::connection('system')->table('marketplace_view_events')
            ->selectRaw('listing_id, hostname_id, metric, DATE(created_at) AS stat_date, COUNT(*) AS n')
            ->where('id', '<=', $maxId)
            ->whereIn('metric', self::METRICS)
            // stat_date es NOT NULL: una fila sin fecha reventaria el upsert y
            // con el la transaccion entera, dejando el buzon atascado para
            // siempre. Se descarta aqui y el DELETE del final se la lleva.
            ->whereNotNull('created_at')
            ->groupByRaw('listing_id, hostname_id, metric, DATE(created_at)')
            ->get();

        $eventos   = 0;
        $productos = [];
        $dias      = 0;

        DB::connection('system')->transaction(function () use ($agregado, $maxId, &$eventos, &$productos, &$dias) {
            foreach ($agregado as $fila) {
                $metric = (string) $fila->metric;
                if (!in_array($metric, self::METRICS, true)) continue;

                $n       = (int) $fila->n;
                $columna = $metric === 'views' ? 'view_count' : 'click_count';

                // El acumulado historico del listing. Si el producto ya no
                // existe el UPDATE afecta 0 filas y el evento se descarta
                // igualmente: esto hace de la FK que la tabla no tiene.
                $tocadas = DB::connection('system')->table('marketplace_listings')
                    ->where('id', $fila->listing_id)
                    ->update([$columna => DB::raw("{$columna} + {$n}")]);

                if ($tocadas === 0) continue;

                // El desglose por dia.
                DB::connection('system')->statement(
                    "INSERT INTO marketplace_listing_stats_daily
                        (listing_id, hostname_id, stat_date, {$metric}, created_at, updated_at)
                     VALUES (?, ?, ?, ?, NOW(), NOW())
                     ON DUPLICATE KEY UPDATE {$metric} = {$metric} + VALUES({$metric}), updated_at = NOW()",
                    [$fila->listing_id, $fila->hostname_id, $fila->stat_date, $n]
                );

                $eventos += $n;
                $productos[$fila->listing_id] = true;
                $dias++;
            }

            DB::connection('system')->table('marketplace_view_events')
                ->where('id', '<=', $maxId)
                ->delete();
        });

        return ['eventos' => $eventos, 'productos' => count($productos), 'dias' => $dias];
    }

    /** Cuantos eventos esperan en el buzon. Para el panel y para diagnostico. */
    public function pending(): int
    {
        return (int) DB::connection('system')->table('marketplace_view_events')->count();
    }
}
