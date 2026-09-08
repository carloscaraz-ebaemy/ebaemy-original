<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saber qué comprobantes se IMPRIMIERON, no solo cuáles existen.
 *
 * ── El hueco ──────────────────────────────────────────────────────────────
 *
 * El rótulo sí lleva registro, y bueno: `shipping_print_events` guarda una fila
 * por impresión con la reimpresión, el motivo, el usuario y la IP (74 eventos
 * en alasitas, 27 de ellos reimpresiones). El comprobante no lleva ninguno:
 * `SaleNoteController::toPrint` y `DownloadController::toPrint` sirven el PDF y
 * no anotan nada.
 *
 * Así que hoy la pregunta «¿ya se imprimió la boleta de este pedido?» no tiene
 * respuesta, y la columna Documentos solo puede decir si el comprobante EXISTE
 * — que es una pregunta distinta y menos útil para despachar.
 *
 * ── Por qué columnas y no una tabla de eventos ────────────────────────────
 *
 * `shipping_print_events` es el patrón que ya existe y sería lo coherente, pero
 * la lectura es lo que manda aquí: `OrderDocuments` YA tiene cargado el
 * registro de cada documento —lo resuelve por tres caminos y está escrito para
 * no disparar consultas por fila— así que dos columnas se leen gratis y una
 * tabla aparte obligaría a precargar un mapa por página.
 *
 * Se pierde el historial. Es un cambio consciente: lo que la operación necesita
 * responder es «¿está impreso?» y «¿cuántas veces?», y eso cabe en dos
 * columnas. Si algún día hace falta el detalle de cada impresión, el patrón a
 * copiar es `shipping_print_events`, no ampliar esto.
 *
 * ── Las tres tablas ───────────────────────────────────────────────────────
 *
 * `sale_notes` (nota de venta), `documents` (boleta y factura) y `dispatches`
 * (guía de remisión electrónica). Son los tres sitios de donde sale un PDF que
 * el operador manda a la impresora, y dejar uno fuera haría que la columna
 * mintiera justo en ese caso.
 *
 * `printed_at` NULL significa «nunca se imprimió». No hay ambigüedad que
 * resolver hacia atrás: nada de lo emitido hasta hoy tiene registro, y
 * inventarlo sería peor que decir la verdad, que es que no se sabe.
 */
return new class extends Migration
{
    /** Los tres orígenes de un PDF imprimible. */
    private const TABLAS = ['sale_notes', 'documents', 'dispatches'];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            // Un tenant puede no tener el módulo: `dispatches` no existe en
            // todos, y una migración que asume la tabla deja el resto sin
            // aplicar por culpa de la primera que falta.
            if (!Schema::connection('tenant')->hasTable($tabla)) {
                continue;
            }

            Schema::connection('tenant')->table($tabla, function (Blueprint $t) use ($tabla) {
                if (!Schema::connection('tenant')->hasColumn($tabla, 'printed_at')) {
                    $t->timestamp('printed_at')->nullable()->after('created_at');
                }

                if (!Schema::connection('tenant')->hasColumn($tabla, 'print_count')) {
                    $t->unsignedInteger('print_count')->default(0)->after('printed_at');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (!Schema::connection('tenant')->hasTable($tabla)) {
                continue;
            }

            Schema::connection('tenant')->table($tabla, function (Blueprint $t) use ($tabla) {
                foreach (['printed_at', 'print_count'] as $col) {
                    if (Schema::connection('tenant')->hasColumn($tabla, $col)) {
                        $t->dropColumn($col);
                    }
                }
            });
        }
    }
};
