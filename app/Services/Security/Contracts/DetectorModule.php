<?php

namespace App\Services\Security\Contracts;

use App\Services\Security\ScanContext;

/**
 * Contrato de un modulo de deteccion.
 *
 * Reglas para cualquier implementacion:
 *  - SOLO LEE. Nunca escribe en la base de datos de produccion ni bloquea nada.
 *  - No lanza excepciones hacia arriba si puede evitarlo; si lo hace, el
 *    orquestador la captura y sigue con los demas modulos.
 *  - Todo umbral sale de la configuracion, nunca del codigo.
 */
interface DetectorModule
{
    /** Clave del modulo en config('security-agent.modules'). */
    public function key(): string;

    /** Nombre legible para el reporte. */
    public function label(): string;

    /** 'system' = corre una vez; 'tenant' = corre una vez por tenant. */
    public function scope(): string;

    /** @return \App\Services\Security\Alert[] */
    public function detect(ScanContext $context): array;
}
