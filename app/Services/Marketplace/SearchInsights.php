<?php

namespace App\Services\Marketplace;

use App\Services\System\MarketplaceListingSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Qué buscan los compradores del marketplace, y qué buscan sin encontrar.
 *
 * El término sin resultados es el dato más accionable que puede dar el
 * buscador: dice qué catálogo falta. Y es la lista con la que se sale a
 * captar tiendas nuevas — «esto lo buscan N veces al mes y nadie lo vende»
 * convence más que cualquier argumento.
 *
 * Nada de esto puede romper una búsqueda: si falla el registro, el comprador
 * tiene que ver sus resultados igual. Por eso todo va envuelto en try/catch y
 * sólo se loguea.
 */
class SearchInsights
{
    private const TABLA = 'marketplace_search_stats_daily';

    /** Más corto que esto es ruido de teclado, no una búsqueda. */
    private const MIN_LONGITUD = 2;

    /**
     * Registra una búsqueda y cuántos resultados devolvió.
     *
     * Una sola consulta, con INSERT ... ON DUPLICATE KEY UPDATE: sin carrera
     * aunque dos visitantes busquen lo mismo a la vez, y sin leer antes para
     * decidir si insertar o actualizar.
     */
    public function record(?string $termino, int $resultados): void
    {
        $bruto = trim(preg_replace('/\s+/', ' ', (string) $termino));

        if (mb_strlen($bruto) < self::MIN_LONGITUD) {
            return;
        }

        $norm = MarketplaceListingSyncService::normalizeForSearch($bruto);
        $norm = mb_substr($norm, 0, 120);

        if ($norm === '') {
            return;
        }

        $sinResultados = $resultados === 0 ? 1 : 0;

        try {
            DB::connection('system')->statement(
                'INSERT INTO ' . self::TABLA . '
                    (stat_date, term_norm, term_sample, searches, zero_results, last_results, created_at, updated_at)
                 VALUES (?, ?, ?, 1, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    searches     = searches + 1,
                    zero_results = zero_results + VALUES(zero_results),
                    last_results = VALUES(last_results),
                    updated_at   = NOW()',
                [
                    now()->toDateString(),
                    $norm,
                    mb_substr($bruto, 0, 160),
                    $sinResultados,
                    max(0, $resultados),
                ]
            );
        } catch (\Throwable $e) {
            // Que no se registre una búsqueda es un agujero en el informe,
            // nunca un error para el comprador.
            Log::warning('SearchInsights: no se pudo registrar la búsqueda', [
                'term'  => $norm,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Lo más buscado en el rango.
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    public function topTerms(string $desde, string $hasta, int $limite = 15)
    {
        return $this->agregado($desde, $hasta)
            ->orderByDesc('searches')
            ->orderBy('term_norm')
            ->limit($limite)
            ->get();
    }

    /**
     * Lo buscado SIN encontrar nada. Es la lista que importa: cada fila es un
     * comprador que vino con una intención concreta y se fue con las manos
     * vacías.
     *
     * Pide que la mayoría de las veces haya fallado (no sólo una), para que
     * un término que ya se resolvió al publicar catálogo deje de aparecer.
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    public function zeroResultTerms(string $desde, string $hasta, int $limite = 15)
    {
        return $this->agregado($desde, $hasta)
            ->havingRaw('SUM(zero_results) > 0')
            ->havingRaw('SUM(zero_results) >= SUM(searches) / 2')
            ->orderByDesc('zero')
            ->orderBy('term_norm')
            ->limit($limite)
            ->get();
    }

    /** Totales del rango, para encabezar el panel. */
    public function summary(string $desde, string $hasta): array
    {
        $fila = DB::connection('system')->table(self::TABLA)
            ->whereBetween('stat_date', [$desde, $hasta])
            ->selectRaw('COALESCE(SUM(searches), 0) as busquedas,
                         COALESCE(SUM(zero_results), 0) as vacias,
                         COUNT(DISTINCT term_norm) as terminos')
            ->first();

        $busquedas = (int) ($fila->busquedas ?? 0);
        $vacias    = (int) ($fila->vacias ?? 0);

        return [
            'searches'      => $busquedas,
            'zero'          => $vacias,
            'terms'         => (int) ($fila->terminos ?? 0),
            'zero_rate'     => $busquedas > 0 ? round($vacias / $busquedas * 100, 1) : 0.0,
        ];
    }

    /**
     * Base común: agrupa los días del rango por término.
     *
     * `term_sample` se agrega con MAX porque el GROUP BY es por `term_norm` y
     * con `only_full_group_by` (el default de este MySQL 8) una columna suelta
     * en el SELECT da error 1055.
     */
    private function agregado(string $desde, string $hasta)
    {
        return DB::connection('system')->table(self::TABLA)
            ->whereBetween('stat_date', [$desde, $hasta])
            ->selectRaw('term_norm,
                         MAX(term_sample) as sample,
                         SUM(searches) as searches,
                         SUM(zero_results) as zero,
                         MAX(last_results) as last_results')
            ->groupBy('term_norm');
    }
}
