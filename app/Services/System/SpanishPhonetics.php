<?php

namespace App\Services\System;

/**
 * Tolerancia a las faltas de ortografia que suenan igual en castellano.
 *
 * El caso que lo motiva, reportado el 2026-10-05: quien busca «cogines» no
 * encuentra nada, aunque el catalogo este lleno de «cojines». Se pronuncia
 * igual y se escribe distinto, y el buscador no ofrecia ninguna alternativa.
 * Lo mismo pasa con «baso»/«vaso», «sapato»/«zapato», «yave»/«llave» o
 * «almoada»/«almohada».
 *
 * No es un corrector ortografico y no pretende serlo: no arregla letras
 * cambiadas de sitio ni omitidas («cojnies»). Cubre, y solo eso, las
 * EQUIVALENCIAS FONETICAS del espanol de Peru, que son las que hacen que dos
 * grafias distintas se lean igual:
 *
 *   g/j ante e,i      cogines  = cojines
 *   b/v               baso     = vaso
 *   s/z/c ante e,i    sapato   = zapato,   cosina = cocina
 *   ll/y (yeismo)     yave     = llave
 *   c/k/qu            kasa     = casa,     keso   = queso
 *   h muda            almoada  = almohada, ora   = hora
 *   rr/r              caro     = carro
 *
 * Se usa de dos formas:
 *
 *   key()              forma canonica. Dos palabras que suenan igual dan la
 *                      misma clave. Sirve para decidir equivalencia.
 *   spellingVariants() grafias alternativas plausibles de lo que se tecleo,
 *                      para buscarlas en texto que NO esta normalizado
 *                      foneticamente. Es lo que consume el buscador: asi no
 *                      hace falta columna nueva ni reindexar el catalogo.
 *
 * Todo opera sobre ASCII: normalize() quita tildes y pasa la ene a «n», igual
 * que MarketplaceListingSyncService::normalizeForSearch, con la que tiene que
 * seguir siendo compatible porque `search_text` se indexa con aquella.
 */
class SpanishPhonetics
{
    /**
     * Sustituciones de una sola letra (o digrafo) que no cambian como se lee
     * la palabra. Cada par va en los DOS sentidos porque no sabemos cual de
     * las dos grafias tecleo la persona y cual guarda el catalogo.
     *
     * El patron casa EXACTAMENTE el trozo que se reemplaza; el contexto va en
     * lookahead para no consumirlo. Un patron de ancho cero (la «h» que falta)
     * inserta en vez de sustituir.
     *
     * @var array<int,array{0:string,1:string}>
     */
    private const SUBS = [
        // g/j ante e,i: el caso de «cogines». Fuera de ese contexto la g no
        // suena como la j («gato» no es «jato»), asi que el lookahead manda.
        ['/g(?=[ei])/', 'j'],
        ['/j(?=[ei])/', 'g'],

        // b/v: en castellano no se distinguen al hablar.
        ['/b/', 'v'],
        ['/v/', 'b'],

        // Asimilacion nasal: «mb» y «nv» suenan igual, de ahi «imbierno» por
        // «invierno» o «embiar» por «enviar».
        ['/m(?=[bv])/', 'n'],
        ['/n(?=[bv])/', 'm'],

        // Seseo: s, z y c ante e,i son el mismo sonido en Peru.
        ['/z/', 's'],
        ['/c(?=[ei])/', 's'],
        ['/c(?=[ei])/', 'z'],
        ['/s(?=[ei])/', 'c'],
        ['/z(?=[ei])/', 'c'],
        // s -> z solo ante a,o,u: ahi es donde de verdad aparece la z
        // («sapato», «cabesa», «sorro»). Ante e,i la z casi no existe en
        // castellano, y al final de palabra solo generaria basura
        // («cojinez») gastando el cupo de variantes.
        ['/s(?=[aou])/', 'z'],

        // Yeismo: ll e y suenan igual.
        ['/ll/', 'y'],
        ['/y(?=[aeiou])/', 'll'],

        // c/k/qu ante el sonido /k/.
        ['/qu(?=[ei])/', 'k'],
        ['/c(?=[aou])/', 'k'],
        ['/k(?=[aou])/', 'c'],
        ['/k(?=[ei])/', 'qu'],

        // h muda: sobra donde esta y falta donde no esta. La insercion va al
        // principio («ora» -> «hora») y ENTRE VOCALES, que es el caso de
        // «almoada» -> «almohada».
        ['/h/', ''],
        ['/^(?=[aeiou])/', 'h'],
        ['/(?<![qg])(?<=[aeiou])(?=[aeiou])/', 'h'],

        // Consonante doble que no cambia el sonido lo suficiente para que la
        // gente acierte: «caro»/«carro». La r doble solo existe entre vocales,
        // asi que no se genera al principio de palabra («rrosa» no es nada).
        ['/rr/', 'r'],
        ['/(?<=[aeiou])r(?=[aeiou])/', 'rr'],
    ];

    /**
     * Forma canonica: dos palabras que se leen igual devuelven lo mismo.
     *
     * El orden importa. La c ante e,i tiene que resolverse ANTES de que la c
     * se convierta en k, o «cocina» acabaria en «kokina» en vez de «kosina».
     */
    public static function key(string $word): string
    {
        $w = static::normalize($word);
        if ($w === '') {
            return '';
        }

        $w = preg_replace('/h/', '', $w);          // h muda
        $w = preg_replace('/[gj](?=[ei])/', 'j', $w);
        $w = preg_replace('/gu(?=[ei])/', 'g', $w);
        $w = preg_replace('/[csz](?=[ei])/', 's', $w); // seseo, antes de c -> k
        $w = preg_replace('/z/', 's', $w);
        $w = preg_replace('/qu(?=[ei])/', 'k', $w);
        $w = preg_replace('/[cq]/', 'k', $w);
        $w = preg_replace('/v/', 'b', $w);
        $w = preg_replace('/m(?=b)/', 'n', $w);    // «mb» = «nv»
        $w = preg_replace('/ll/', 'y', $w);
        $w = preg_replace('/x/', 'ks', $w);
        $w = preg_replace('/y$/', 'i', $w);
        $w = preg_replace('/(.)\1+/', '$1', $w);   // dobles: rr, cc, nn, ss

        return $w;
    }

    /**
     * Grafias alternativas de lo que se tecleo, la original primero.
     *
     * Explora en anchura: aplica cada sustitucion a cada posicion donde
     * encaje, de una en una, de modo que una palabra con dos ambiguedades
     * («vaselina»: la v y la s) tambien genera las combinaciones parciales.
     * Se corta en $max porque cada variante son cuatro `LIKE` mas en el SQL de
     * relevancia, y un desplegable de 8 huecos no necesita mas.
     *
     * Solo devuelve variantes que suenen IGUAL que la original: la clave
     * canonica tiene que coincidir. Asi una sustitucion aplicada en un
     * contexto que no tocaba no puede colar una palabra distinta.
     *
     * @return array<int,string>
     */
    public static function spellingVariants(string $word, int $max = 6): array
    {
        $base = static::normalize($word);
        if ($base === '' || mb_strlen($base) < 3) {
            // Con menos de 3 letras cualquier sustitucion genera ruido: «de»
            // daria «te», «se»... y el token es demasiado corto para filtrar.
            return $base === '' ? [] : [$base];
        }

        $clave = static::key($base);
        $vistos = [$base => true];
        $salida = [$base];
        $cola   = [$base];

        while ($cola !== [] && count($salida) < $max) {
            $actual = array_shift($cola);

            foreach (static::SUBS as [$patron, $reemplazo]) {
                if (!preg_match_all($patron, $actual, $m, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                foreach ($m[0] as [$trozo, $offset]) {
                    $variante = substr_replace($actual, $reemplazo, $offset, strlen($trozo));

                    if ($variante === '' || isset($vistos[$variante])) {
                        continue;
                    }
                    // La red de seguridad: si no suena igual, no es una
                    // variante ortografica, es otra palabra.
                    if (static::key($variante) !== $clave) {
                        continue;
                    }

                    $vistos[$variante] = true;
                    $salida[] = $variante;
                    $cola[]   = $variante;

                    if (count($salida) >= $max) {
                        return $salida;
                    }
                }
            }
        }

        return $salida;
    }

    /**
     * Minusculas, sin tildes y sin ene, igual que
     * MarketplaceListingSyncService::normalizeForSearch. El resultado es ASCII,
     * asi que las variantes se pueden recortar por bytes sin romper nada.
     */
    public static function normalize(string $word): string
    {
        $w = mb_strtolower(trim($word), 'UTF-8');

        $w = strtr($w, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ]);

        // Lo que no sea letra ASCII o digito sobra: estas variantes acaban
        // interpoladas en un LIKE.
        return preg_replace('/[^a-z0-9]/', '', $w);
    }
}
