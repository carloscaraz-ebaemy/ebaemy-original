<?php

namespace App\Services\Marketplace;

use App\Models\System\MarketplaceListing;
use App\Services\System\MarketplaceListingSyncService;
use Illuminate\Support\Facades\Cache;

/**
 * Rescate de una búsqueda que no devolvió nada por una errata.
 *
 * Hoy «zapatila» o «audifnos» devuelven cero, y la pantalla de sin resultados
 * se limita a sugerir «verifica que las palabras estén bien escritas». Esto lo
 * resuelve por el comprador.
 *
 * Sólo corrige contra el vocabulario REAL del catálogo: las palabras que
 * aparecen en los títulos, marcas y categorías de lo que está publicado. Así
 * la corrección nunca propone algo que tampoco existe — si sugiere
 * «zapatillas» es porque hay zapatillas que encontrar.
 *
 * Se invoca únicamente cuando la búsqueda ya dio cero resultados, así que en
 * el camino normal no cuesta nada. Con unos cientos de productos el
 * vocabulario son unos pocos miles de palabras y la comparación es inmediata;
 * si el catálogo creciera mucho, este es el punto a revisar.
 */
class SearchSpellRescue
{
    /** Palabras más cortas que esto no se corrigen: cambiarlas es adivinar. */
    private const MIN_LONGITUD = 4;

    /** Cuántas palabras del catálogo se guardan como mucho. */
    private const MAX_VOCABULARIO = 8000;

    private const CACHE_KEY = 'mp_search_vocabulario_v1';
    private const CACHE_TTL = 3600;

    /**
     * Devuelve la consulta corregida, o null si no hay nada que corregir o no
     * se encontró un candidato lo bastante parecido.
     */
    public function suggest(?string $consulta): ?string
    {
        $consulta = trim(preg_replace('/\s+/', ' ', (string) $consulta));

        if ($consulta === '') {
            return null;
        }

        $vocabulario = $this->vocabulario();

        if (empty($vocabulario)) {
            return null;
        }

        $palabras  = explode(' ', $consulta);
        $corregida = [];
        $cambios   = 0;

        foreach ($palabras as $palabra) {
            $norm = MarketplaceListingSyncService::normalizeForSearch($palabra);

            // Ya existe en el catálogo, o es demasiado corta para arriesgarse.
            if (mb_strlen($norm) < self::MIN_LONGITUD || isset($vocabulario[$norm])) {
                $corregida[] = $palabra;
                continue;
            }

            $candidato = $this->masParecida($norm, $vocabulario);

            if ($candidato === null) {
                $corregida[] = $palabra;
                continue;
            }

            $corregida[] = $candidato;
            $cambios++;
        }

        if ($cambios === 0) {
            return null;
        }

        $resultado = implode(' ', $corregida);

        return $resultado !== $consulta ? $resultado : null;
    }

    /**
     * La palabra del catálogo más parecida, dentro de una distancia que
     * depende de lo larga que sea: en una palabra corta un solo carácter de
     * diferencia ya puede ser otra palabra distinta («mesa» / «misa»), y en
     * una larga dos erratas siguen siendo la misma intención.
     *
     * El criterio que decide los empates es el **prefijo común**, no la
     * frecuencia. Probado contra el catálogo real, desempatar por frecuencia
     * daba correcciones absurdas: «zapatila» → «zapatera» (un mueble) en vez
     * de «zapatillas», y «poloo» → «pollo» en vez de «polo». Los dos
     * candidatos estaban a la misma distancia y ganaba el que más aparecía en
     * el catálogo, que no tiene nada que ver con lo que el comprador quiso
     * escribir. Las erratas se cometen al final o en medio de la palabra, casi
     * nunca al principio, así que el prefijo compartido es mucho mejor señal.
     */
    private function masParecida(string $palabra, array $vocabulario): ?string
    {
        $largo  = mb_strlen($palabra);
        $umbral = $largo <= 5 ? 1 : ($largo <= 8 ? 2 : 3);

        // Sin un principio en común no es una errata, es otra palabra. Esto
        // descarta «cartea» → «parte», que la distancia admitía.
        //
        // Cuatro caracteres y no tres, también por lo que se vio probando:
        // con tres, «poloo» se corregia a «pollo». El rescate prefiere
        // quedarse corto — una correccion equivocada manda al comprador a un
        // producto que no tiene nada que ver y es peor que no corregir, porque
        // la pantalla vacia al menos dice la verdad.
        $prefijoMinimo = min(4, max(1, $largo - 1));

        $mejor          = null;
        $mejorDistancia = PHP_INT_MAX;
        $mejorPrefijo   = -1;
        $mejorPeso      = 0;

        foreach ($vocabulario as $termino => $peso) {
            $termino = (string) $termino;

            // Descarte barato antes de calcular: una diferencia de longitud
            // mayor que el umbral ya garantiza que la distancia lo supera.
            if (abs(mb_strlen($termino) - $largo) > $umbral) {
                continue;
            }

            $d = levenshtein($palabra, $termino);

            if ($d > $umbral) {
                continue;
            }

            $prefijo = $this->prefijoComun($palabra, $termino);

            if ($prefijo < $prefijoMinimo) {
                continue;
            }

            // Orden de preferencia: más cerca, luego más principio en común,
            // y sólo si todo empata, lo que más abunda en el catálogo.
            $mejorQueActual = $d < $mejorDistancia
                || ($d === $mejorDistancia && $prefijo > $mejorPrefijo)
                || ($d === $mejorDistancia && $prefijo === $mejorPrefijo && $peso > $mejorPeso);

            if ($mejorQueActual) {
                $mejor          = $termino;
                $mejorDistancia = $d;
                $mejorPrefijo   = $prefijo;
                $mejorPeso      = $peso;
            }
        }

        return $mejor;
    }

    /** Cuántos caracteres comparten dos palabras desde el principio. */
    private function prefijoComun(string $a, string $b): int
    {
        $n = min(mb_strlen($a), mb_strlen($b));

        for ($i = 0; $i < $n; $i++) {
            if (mb_substr($a, $i, 1) !== mb_substr($b, $i, 1)) {
                return $i;
            }
        }

        return $n;
    }

    /**
     * Palabras del catálogo publicado, con cuántas veces aparecen.
     *
     * `protected` para que un test pueda sustituirlo por un vocabulario
     * conocido y probar las reglas de corrección sin depender de lo que
     * haya publicado en ese momento.
     *
     * @return array<string,int> palabra normalizada => frecuencia
     */
    protected function vocabulario(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $frecuencias = [];

            MarketplaceListing::published()
                ->select('title', 'brand_name', 'category_name')
                ->chunk(500, function ($filas) use (&$frecuencias) {
                    foreach ($filas as $fila) {
                        $texto = trim(
                            ($fila->title ?? '') . ' ' .
                            ($fila->brand_name ?? '') . ' ' .
                            ($fila->category_name ?? '')
                        );

                        $norm = MarketplaceListingSyncService::normalizeForSearch($texto);

                        // Fuera signos: «zapatillas,» y «zapatillas» son la misma.
                        $norm = preg_replace('/[^a-z0-9ñ ]+/u', ' ', $norm);

                        foreach (explode(' ', $norm) as $palabra) {
                            if (mb_strlen($palabra) < self::MIN_LONGITUD) {
                                continue;
                            }

                            $frecuencias[$palabra] = ($frecuencias[$palabra] ?? 0) + 1;
                        }
                    }
                });

            arsort($frecuencias);

            return array_slice($frecuencias, 0, self::MAX_VOCABULARIO, true);
        });
    }

    /** Para cuando se publica o retira catálogo y el vocabulario cambia. */
    public function olvidarVocabulario(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
