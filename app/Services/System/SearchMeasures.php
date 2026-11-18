<?php

namespace App\Services\System;

/**
 * Medidas de longitud escritas de muchas formas: «2.40», «2,40 m», «240cm»,
 * «2metros» y «2 m» tienen que casar entre sí.
 *
 * El buscador compara texto, y «arbol de navidad 2.40» no encontraba un
 * «Pino… 240 cm» (ni «arbol 2metros» un «… 2 m»). La solución es llevar las
 * dos orillas a la MISMA forma canónica: centímetros enteros pegados a la
 * unidad, «240cm». Se aplica en el índice (`search_text`, al sincronizar) y en
 * la consulta (MarketplaceListing::searchTokens), así que una tienda escribe
 * su medida como quiera y el comprador la busca como quiera.
 *
 * Criterio y límites, a propósito:
 *  - Solo LONGITUD en metros y centímetros (m, mt, mts, metro(s), cm, cms,
 *    centímetro(s)). Ni mm, ni pulgadas, ni litros, ni m2/m3: «2 m2» se deja
 *    tal cual porque la unidad no termina ahí.
 *  - El resultado tiene que ser un número ENTERO de centímetros. «1.5 cm» no
 *    se toca (no hay forma canónica sin punto, y el buscador quita los
 *    puntos); «1.25 m» sí: 125cm.
 *  - La coma decimal entre dígitos se lee como punto («2,40» = «2.40») si
 *    lleva 1 o 2 decimales. «1,200» (miles) se queda como está.
 *  - Una medida pegada a otra cifra o letra no se toca: «2x2 m», «x24 m».
 *  - «m» sola tras un número se lee como metros: «polo 2 m» se volvería
 *    «200cm». Es raro en un título de ropa (la talla va sin número), y casar
 *    «2 m» con «2 metros» vale más.
 *  - Un número SIN unidad en el índice no se toca: «Árbol 2.40» puede ser un
 *    precio o un código. En la consulta, en cambio, un decimal suelto entre
 *    0,3 y 9,99 («arbol 2.40») se prueba ADEMÁS como metros (alternatives),
 *    sin dejar de buscar el número tal cual.
 *  - Igualdad exacta, no rangos: «2metros» (200cm) NO casa con «2.1 m»
 *    (210cm). Para eso está la búsqueda flexible, que con «arbol» ya trae los
 *    árboles aunque la medida no coincida.
 */
class SearchMeasures
{
    private const UNIDADES_M  = ['m', 'mt', 'mts', 'metro', 'metros'];
    private const UNIDADES_CM = ['cm', 'cms', 'centimetro', 'centimetros', 'centímetro', 'centímetros'];

    /**
     * Lleva toda medida del texto a «<n>cm». Sirve para texto ya normalizado
     * (minúsculas, sin tildes) y para lo que teclea el comprador.
     */
    public static function normalize(string $text): string
    {
        if ($text === '' || !preg_match('/\d/', $text)) {
            return $text;
        }

        // Coma decimal entre dígitos → punto: «2,40 m» → «2.40 m». Antes de
        // nada, porque el tokenizador parte por comas.
        $text = static::decimalComma($text);

        $unidades = implode('|', array_map(
            fn ($u) => preg_quote($u, '/'),
            // Las largas primero: «metros» antes que «m».
            static::sortByLength(array_merge(self::UNIDADES_M, self::UNIDADES_CM))
        ));

        return preg_replace_callback(
            '/(?<![\p{L}\p{N}.,])(\d{1,4}(?:\.\d{1,2})?)\s?(' . $unidades . ')(?![\p{L}\p{N}])/iu',
            function ($m) {
                $cm = static::toCentimeters($m[1], $m[2]);
                return $cm === null ? $m[0] : $cm . 'cm';
            },
            $text
        );
    }

    /**
     * «2,40» → «2.40» cuando la coma separa decimales (1 o 2 cifras detrás).
     */
    public static function decimalComma(string $text): string
    {
        return preg_replace('/(?<=\d),(?=\d{1,2}(?!\d))/u', '.', $text);
    }

    /**
     * Formas extra con que buscar un token suelto: un decimal sin unidad que
     * tiene pinta de metros («2.40», «1.8») se prueba también como «240cm»;
     * y un «240cm» también como «240 cm». Se SUMAN a la búsqueda del token
     * tal cual, nunca lo sustituyen.
     *
     * @return array<int,string>
     */
    public static function alternatives(string $token): array
    {
        $token = static::decimalComma(mb_strtolower(trim($token), 'UTF-8'));

        // Ya canónico («240cm»): se prueba también separado, «240 cm», para
        // los índices que aún no se han vuelto a sincronizar.
        if (preg_match('/^(\d{1,5})cm$/', $token, $m)) {
            return [$m[1] . ' cm'];
        }

        if (!preg_match('/^\d{1,2}\.\d{1,2}$/', $token)) {
            return [];
        }

        $valor = (float) $token;
        if ($valor < 0.3 || $valor > 9.99) {
            return [];
        }

        return [((int) round($valor * 100)) . 'cm'];
    }

    /** Centímetros enteros, o null si no salen enteros. */
    private static function toCentimeters(string $numero, string $unidad): ?int
    {
        $unidad = mb_strtolower($unidad, 'UTF-8');
        $valor  = (float) $numero;
        if ($valor <= 0) {
            return null;
        }

        $cm = in_array($unidad, self::UNIDADES_M, true) ? $valor * 100 : $valor;
        $entero = (int) round($cm);

        // Solo si es exacto: «1.5 cm» no tiene forma entera.
        return abs($cm - $entero) < 0.0001 ? $entero : null;
    }

    /** @param array<int,string> $palabras */
    private static function sortByLength(array $palabras): array
    {
        usort($palabras, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return $palabras;
    }
}
