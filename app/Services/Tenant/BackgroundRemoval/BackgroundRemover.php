<?php

namespace App\Services\Tenant\BackgroundRemoval;

/**
 * Un proveedor que quita el fondo de una foto de producto.
 *
 * Recibe los bytes de la imagen y devuelve los de la imagen sobre fondo
 * BLANCO (no transparente): el catalogo pide blanco, y el pipeline guarda sin
 * alfa. Cambiar de proveedor es escribir otra implementacion; los formularios
 * no se enteran.
 */
interface BackgroundRemover
{
    /** @throws BackgroundRemovalException con un mensaje que se puede enseñar al vendedor */
    public function remove(string $contenido): string;
}
