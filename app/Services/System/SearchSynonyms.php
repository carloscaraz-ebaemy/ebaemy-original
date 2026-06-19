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

    /**
     * Devuelve el token normalizado + sus sinónimos (también normalizados).
     */
    public static function expand(string $token): array
    {
        $norm = MarketplaceListingSyncService::normalizeForSearch($token);
        $out = [$norm];
        foreach (static::$map[$norm] ?? [] as $syn) {
            $out[] = MarketplaceListingSyncService::normalizeForSearch($syn);
        }

        return array_values(array_filter(array_unique($out), fn ($t) => $t !== ''));
    }
}
