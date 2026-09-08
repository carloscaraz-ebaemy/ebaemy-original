<?php

namespace App\Services\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Anotar que un comprobante se mandó a imprimir.
 *
 * ── Por qué hace falta ────────────────────────────────────────────────────
 *
 * «Generado» e «impreso» son dos hechos distintos y hasta ahora el sistema solo
 * sabía el primero. Un comprobante emitido y nunca impreso no ha llegado a la
 * caja del paquete: para despachar, la pregunta útil es la segunda.
 *
 * El rótulo ya lo resuelve `ShippingPrintEvent` desde hace tiempo. Esto es lo
 * mismo para los tres orígenes de PDF que faltaban.
 *
 * ── Nunca puede tumbar una impresión ──────────────────────────────────────
 *
 * Se llama justo antes de devolver el PDF. Si algo falla aquí —un tenant con
 * `tenancy:migrate` atrasado, un `documents` sin la columna— el operador se
 * quedaría sin su boleta por culpa de una anotación, que es un intercambio
 * absurdo. Por eso todo va dentro de un try/catch y comprobando el esquema
 * antes: si no se puede anotar, se imprime igual y queda el aviso en el log.
 *
 * ── No se usa el modelo ───────────────────────────────────────────────────
 *
 * Se escribe con el query builder y no con `$modelo->save()` a propósito:
 * `Document` y `SaleNote` arrastran observers, mutadores y lógica de
 * recálculo, y un guardado completo por cada impresión podría disparar efectos
 * que nadie pidió. Un UPDATE de dos columnas es exactamente lo que hay que
 * hacer.
 */
class DocumentPrints
{
    /**
     * Deja constancia de una impresión.
     *
     * @param Model|null $registro El comprobante que se acaba de servir.
     */
    public static function marcar($registro): void
    {
        if (!$registro || !$registro->exists) {
            return;
        }

        try {
            $tabla = $registro->getTable();

            if (!Schema::connection('tenant')->hasColumn($tabla, 'printed_at')) {
                return;
            }

            DB::connection('tenant')->table($tabla)
                ->where($registro->getKeyName(), $registro->getKey())
                ->update([
                    'printed_at'  => now(),
                    // Incremento en SQL y no en PHP: dos pestañas abriendo el
                    // mismo PDF a la vez se contarían una sola.
                    'print_count' => DB::raw('COALESCE(print_count, 0) + 1'),
                ]);
        } catch (\Throwable $e) {
            // El PDF ya está listo: que no se quede sin imprimir por esto.
            Log::warning('[DocumentPrints] no se pudo anotar la impresión de '
                . get_class($registro) . '#' . $registro->getKey() . ': ' . $e->getMessage());
        }
    }
}
