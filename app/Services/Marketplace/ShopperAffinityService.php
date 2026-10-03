<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Collection;

/**
 * Afinidad del visitante: que ha buscado y que ha visto en esta sesion, para
 * ordenar escaparates (hoy "Ofertas del dia") por lo que de verdad le
 * interesa en vez de servir a todos la misma vitrina.
 *
 * Dos fuentes, las dos de sesion — sin tabla y sin dato personal:
 *   · los terminos que paso por el buscador (`?q=`), que son intencion
 *     explicita y por eso pesan mas;
 *   · el texto de los productos que vio, via RecentlyViewedService.
 *
 * NO filtra: solo reordena. Una oferta sin afinidad baja, nunca desaparece,
 * asi que el escaparate sigue mostrando todas las tiendas. Si no hay señales
 * (primera visita) devuelve la coleccion tal cual la dio el servidor, que ya
 * viene intercalada por tienda.
 */
class ShopperAffinityService
{
    public const SESSION_KEY = 'mp_recent_queries';
    public const MAX_QUERIES = 6;

    /** Tokens de menos de 3 letras ("de", "la") casarian con casi todo. */
    private const MIN_TOKEN = 3;

    /**
     * Registra un termino buscado (LRU, el mas reciente primero).
     */
    public function pushQuery(?string $q): void
    {
        $q = trim(preg_replace('/\s+/', ' ', (string) $q));
        if ($q === '' || mb_strlen($q) < 2) return;

        $q    = mb_substr($q, 0, 60);
        $low  = mb_strtolower($q);
        $prev = session(self::SESSION_KEY, []);
        if (!is_array($prev)) $prev = [];

        $prev = array_values(array_filter(
            $prev,
            fn ($t) => is_string($t) && mb_strtolower($t) !== $low
        ));
        array_unshift($prev, $q);

        session([self::SESSION_KEY => array_slice($prev, 0, self::MAX_QUERIES)]);
    }

    /**
     * Terminos buscados, el mas reciente primero.
     *
     * @return array<int,string>
     */
    public function queries(): array
    {
        $q = session(self::SESSION_KEY, []);
        if (!is_array($q)) return [];

        return array_values(array_filter($q, fn ($t) => is_string($t) && trim($t) !== ''));
    }

    /**
     * Tokens pesados en DOS tramos: lo buscado y lo visto. Separados a
     * proposito — sumarlos en un solo numero deja que un producto visto con
     * titulo + categoria largos (muchos tokens) adelante a lo que el
     * comprador acaba de teclear, que es su intencion explicita. Mismo
     * criterio que MarketplaceListing::textRelevanceSql, que puntua el
     * titulo en tramos propios por encima de la categoria.
     *
     * @param  Collection|null  $viewed  Listings vistos; se pasa desde fuera
     *                                   para no repetir la consulta que la
     *                                   home ya hace para "Vistos
     *                                   recientemente". Null = no mirarlos.
     * @return array{query: array<string,int>, viewed: array<string,int>}
     */
    public function termWeights(?Collection $viewed = null): array
    {
        $buckets = ['query' => [], 'viewed' => []];

        $add = function (string $bucket, ?string $text, int $weight) use (&$buckets) {
            foreach ($this->tokenize($text) as $tok) {
                // Un token repetido se queda con su mejor peso, no se acumula:
                // asi una categoria larga no gana a la intencion de busqueda.
                $buckets[$bucket][$tok] = max($buckets[$bucket][$tok] ?? 0, $weight);
            }
        };

        foreach ($this->queries() as $i => $term) {
            $add('query', $term, 3 - min($i, 2)); // 3, 2, 1, 1, 1, 1
        }

        if ($viewed) {
            foreach ($viewed->values() as $i => $listing) {
                $text = trim(implode(' ', array_filter([
                    $listing->title ?? null,
                    $listing->category_name ?? null,
                    $listing->brand_name ?? null,
                ])));
                $add('viewed', $text, $i < 4 ? 2 : 1);
            }
        }

        return $buckets;
    }

    /**
     * Reordena las ofertas por afinidad, manteniendo el intercalado de
     * tiendas entre las afines para que el arranque del carrusel no quede
     * en manos de una sola. Las que no casan con nada conservan su orden.
     */
    public function rankOffers(Collection $offers, ?Collection $viewed = null): Collection
    {
        if ($offers->count() < 3) return $offers;

        $weights = $this->termWeights($viewed);
        if (empty($weights['query']) && empty($weights['viewed'])) return $offers;

        $hits = [];
        $rest = [];
        foreach ($offers->values() as $idx => $offer) {
            $haystack = $this->offerText($offer);
            $byQuery  = $this->scoreAgainst($haystack, $weights['query']);
            $byViewed = $this->scoreAgainst($haystack, $weights['viewed']);
            if ($byQuery > 0 || $byViewed > 0) {
                $hits[] = [
                    'offer' => $offer,
                    'q'     => $byQuery,
                    'v'     => $byViewed,
                    'idx'   => $idx,
                ];
            } else {
                $rest[] = $offer;
            }
        }

        if (empty($hits)) return $offers; // nada afin: no se toca nada

        // Lo buscado primero; lo visto solo desempata. Y a igualdad, el orden
        // que venia del servidor.
        usort($hits, fn ($a, $b) => [$b['q'], $b['v'], $a['idx']] <=> [$a['q'], $a['v'], $b['idx']]);

        // Agrupar por tienda preservando el orden por puntuacion y luego
        // intercalar: tienda A, B, C, A, B… igual que hace el reparto base.
        $byTenant = [];
        foreach ($hits as $hit) {
            $byTenant[$hit['offer']->hostname_id ?? 0][] = $hit['offer'];
        }

        $ordered = collect();
        for ($round = 0; ; $round++) {
            $pushedAny = false;
            foreach ($byTenant as $tenantOffers) {
                if (isset($tenantOffers[$round])) {
                    $ordered->push($tenantOffers[$round]);
                    $pushedAny = true;
                }
            }
            if (!$pushedAny) break;
        }

        foreach ($rest as $offer) {
            $ordered->push($offer);
        }

        return $ordered;
    }

    /**
     * Texto con el que se compara una oferta: titulo + categoria + marca.
     */
    private function offerText($offer): string
    {
        return mb_strtolower(trim(implode(' ', array_filter([
            $offer->title ?? null,
            $offer->category_name ?? null,
            $offer->brand_name ?? null,
        ]))));
    }

    /**
     * Puntua un texto contra un mapa de tokens. Acertar una palabra entera
     * vale el doble que casar a mitad de palabra: "polo" no deberia puntuar
     * igual en "polo" que en "espolon" — el mismo criterio que usa
     * MarketplaceListing::textRelevanceSql.
     *
     * @param  array<string,int>  $weights
     */
    private function scoreAgainst(string $haystack, array $weights): int
    {
        if ($haystack === '' || empty($weights)) return 0;

        $score = 0;
        foreach ($weights as $tok => $weight) {
            if (mb_strpos($haystack, (string) $tok) === false) continue;
            $whole = (bool) preg_match(
                '/(?:^|[^\p{L}\p{N}])' . preg_quote((string) $tok, '/') . '(?:[^\p{L}\p{N}]|$)/u',
                $haystack
            );
            $score += $weight * ($whole ? 2 : 1);
        }

        return $score;
    }

    /**
     * Palabras de 3+ caracteres, en minusculas, sin signos.
     *
     * @return array<int,string>
     */
    private function tokenize(?string $text): array
    {
        $text = mb_strtolower(trim((string) $text));
        if ($text === '') return [];

        $parts = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $parts,
            fn ($p) => mb_strlen($p) >= self::MIN_TOKEN
        )));
    }
}
