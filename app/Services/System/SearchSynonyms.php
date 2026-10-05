<?php

namespace App\Services\System;

/**
 * Diccionario de sinónimos / términos relacionados para el buscador del
 * marketplace. Permite que "asiento" también encuentre "silla", etc.
 *
 * Las claves y valores se comparan ya normalizados (sin acentos, minúsculas)
 * vía MarketplaceListingSyncService::normalizeForSearch. Es bidireccional por
 * convención (declara ambos sentidos para resultados consistentes).
 */
class SearchSynonyms
{
    protected static array $map = [
        'asiento'     => ['silla', 'sillon', 'butaca', 'taburete'],
        'silla'       => ['asiento', 'sillon', 'butaca', 'taburete'],
        'sillon'      => ['silla', 'asiento', 'butaca'],
        'polo'        => ['camiseta', 'tshirt', 'remera'],
        'camiseta'    => ['polo', 'tshirt', 'remera'],
        'audifonos'   => ['auriculares', 'cascos', 'earphones', 'headset', 'audifono'],
        'auriculares' => ['audifonos', 'cascos', 'earphones', 'headset'],
        'celular'     => ['telefono', 'smartphone', 'movil', 'equipo'],
        'telefono'    => ['celular', 'smartphone', 'movil'],
        'laptop'      => ['notebook', 'portatil', 'computadora', 'pc'],
        'computadora' => ['laptop', 'pc', 'notebook', 'ordenador'],
        'zapatilla'   => ['zapatillas', 'tenis', 'sneaker', 'zapato', 'calzado'],
        'zapatillas'  => ['zapatilla', 'tenis', 'sneaker', 'calzado'],
        'cartera'     => ['bolso', 'bolsa', 'monedero'],
        'bolso'       => ['cartera', 'bolsa', 'mochila'],
        'reloj'       => ['watch', 'smartwatch'],
        'smartwatch'  => ['reloj', 'watch'],
        'lentes'      => ['gafas', 'anteojos', 'lente'],
        'gafas'       => ['lentes', 'anteojos'],
        'mochila'     => ['morral', 'backpack', 'bolso'],
        'olla'        => ['cacerola', 'perol', 'caldero'],
        'licuadora'   => ['batidora'],
        'cafetera'    => ['cafe', 'greca'],
        'audifono'    => ['audifonos', 'auricular'],
        'cargador'    => ['adaptador', 'charger'],
        'parlante'    => ['altavoz', 'speaker', 'bocina'],
        'casaca'      => ['chaqueta', 'campera', 'abrigo'],
        'chaqueta'    => ['casaca', 'campera', 'abrigo'],
        'pantalon'    => ['jean', 'pantalones'],
        'jean'        => ['pantalon', 'pantalones'],
        'cama'        => ['colchon', 'somier'],
        'colchon'     => ['cama'],
    ];

    /** Tope de variantes que devuelve expand(), para no inflar el SQL. */
    private const MAX_VARIANTES = 12;

    /**
     * La MISMA palabra escrita de otras formas: el token normalizado, su
     * singular, y las grafías que suenan igual.
     *
     * Se separa de expand() porque son dos cosas distintas y no todo el mundo
     * quiere las dos. Esto son erratas y plurales — «cogines» es «cojines» —, y
     * ampliarlo nunca cambia QUÉ se buscó. Los sinónimos de expand() sí: traen
     * productos que la persona no nombró. La tienda del tenant usa solo esto.
     *
     * @return array<int,string>
     */
    public static function spellings(string $token): array
    {
        $norm = MarketplaceListingSyncService::normalizeForSearch($token);
        if ($norm === '') {
            return [];
        }

        $out = [$norm];

        $singular = static::singular($norm);
        if ($singular) {
            $out[] = $singular;
        }

        // Quien busca «cogines» tiene que encontrar los «cojines» del catálogo.
        // Se piden también las del singular porque el catálogo guarda las dos
        // formas y no sabemos cuál.
        foreach (array_filter([$norm, $singular]) as $base) {
            foreach (SpanishPhonetics::spellingVariants($base, 4) as $variante) {
                if ($variante !== '' && $variante !== $base) {
                    $out[] = $variante;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Todo lo anterior MÁS los sinónimos de significado (asiento → silla).
     *
     * Lo consumen a la vez `MarketplaceListing::scopeSearch` (filtro) y
     * `textRelevanceSql` (orden), así que lo que se añada aquí lo heredan los
     * dos sin tocarlos.
     */
    public static function expand(string $token): array
    {
        $out = [];

        // A cada grafía se le miran sus sinónimos, no solo a la que se tecleó:
        // así una palabra mal escrita llega igual de lejos que la bien escrita
        // («sapato» → «zapato» → calzado).
        foreach (static::spellings($token) as $grafia) {
            $out[] = $grafia;

            foreach (static::$map[$grafia] ?? [] as $syn) {
                $out[] = MarketplaceListingSyncService::normalizeForSearch($syn);
            }
        }

        if ($out === []) {
            $out = [MarketplaceListingSyncService::normalizeForSearch($token)];
        }

        $limpio = array_values(array_filter(array_unique($out), fn ($t) => $t !== ''));

        return array_slice($limpio, 0, self::MAX_VARIANTES);
    }

    /**
     * Singular aproximado de una palabra en español, o null si no procede.
     *
     * Hace falta porque el buscador casa con `LIKE '%token%'`: «maceta»
     * aparece dentro de «Macetas», pero «macetas» NO aparece dentro de
     * «Maceta». Medido en producción el 2026-10-03: «maceta» devolvía 25
     * resultados y «macetas» sólo 3, con el mismo catálogo. Lo mismo con
     * alfombra/alfombras y cojin/cojines. Y la gente busca en plural.
     *
     * La variante se AÑADE como alternativa, nunca sustituye al término
     * escrito, así que esto sólo puede sumar resultados, no quitarlos.
     *
     * No es un lematizador: para un catálogo de productos basta con quitar
     * la marca de plural más común.
     */
    public static function singular(string $palabra): ?string
    {
        $largo = mb_strlen($palabra);

        // «cojines» -> «cojin», «manteles» -> «mantel». Pedimos 6 para no
        // destrozar palabras cortas donde «es» no es plural («mes», «tres»).
        if ($largo >= 6 && str_ends_with($palabra, 'es')) {
            return mb_substr($palabra, 0, -2);
        }

        // «macetas» -> «maceta». Desde 5 para no tocar «mas» o «pais».
        if ($largo >= 5 && str_ends_with($palabra, 's')) {
            return mb_substr($palabra, 0, -1);
        }

        return null;
    }
}
