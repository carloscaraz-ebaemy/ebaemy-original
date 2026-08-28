<?php

namespace App\Services\Tenant;

use App\Models\Tenant\ShippingAuditLog;
use App\Models\Tenant\ShippingPrintBatch;
use App\Models\Tenant\ShippingPrintEvent;
use App\Models\Tenant\ShippingRequest;
use App\Models\Tenant\ShippingSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de negocio de los lotes de impresión.
 *
 * Concentra aquí lo que no debe vivir en el controlador:
 *   · Un envío solo puede estar en UN lote activo.
 *   · Un lote ya impreso no admite envíos nuevos ni cambios de modalidad.
 *   · El recojo en tienda no genera rótulo de transporte ni manifiesto.
 *   · La hora de corte decide qué entra al lote del día.
 *
 * Toda operación deja rastro en ShippingAuditLog.
 */
class ShippingBatchService
{
    /**
     * Crea un lote ABIERTO con los envíos indicados.
     *
     * @param  array<int>  $ids
     * @return array{batch: ShippingPrintBatch|null, added: int, skipped: array<string, string>}
     */
    public function createBatch(array $ids, ?string $format = 'a5', ?string $notes = null): array
    {
        $ids = collect($ids)->map(fn ($x) => (int) $x)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return ['batch' => null, 'added' => 0, 'skipped' => []];
        }

        return DB::connection('tenant')->transaction(function () use ($ids, $format, $notes) {
            $shipments = ShippingRequest::whereIn('id', $ids)->lockForUpdate()->get();

            $skipped   = [];
            $eligible  = collect();

            foreach ($shipments as $shipment) {
                $reason = $this->rejectionReason($shipment);

                if ($reason) {
                    $skipped[$shipment->shipment_code ?: $shipment->id] = $reason;
                    continue;
                }

                $eligible->push($shipment);
            }

            if ($eligible->isEmpty()) {
                return ['batch' => null, 'added' => 0, 'skipped' => $skipped];
            }

            $user = auth()->user();

            $batch = ShippingPrintBatch::create([
                'code'            => ShippingPrintBatch::nextCode(),
                'status'          => ShippingPrintBatch::STATUS_OPEN,
                'delivery_type'   => $this->dominantType($eligible),
                'format'          => $format,
                'notes'           => $notes,
                'created_by'      => $user?->id,
                'created_by_name' => $user?->name,
                'label_count'     => $eligible->count(),
                'package_count'   => (int) $eligible->sum(fn ($s) => max(1, (int) $s->package_count)),
            ]);

            foreach ($eligible as $shipment) {
                $shipment->forceFill(['print_batch_id' => $batch->id])->save();

                ShippingAuditLog::log(
                    ShippingAuditLog::ACTION_BATCH_ADD,
                    $shipment->id,
                    'print_batch_id',
                    null,
                    $batch->code,
                    null,
                    $batch->id
                );
            }

            $batch->update(['label_range' => $batch->computeLabelRange()]);

            ShippingAuditLog::log(
                ShippingAuditLog::ACTION_BATCH_OPEN,
                null,
                'status',
                null,
                $batch->code,
                "{$eligible->count()} envío(s) en el lote",
                $batch->id
            );

            return ['batch' => $batch->fresh(), 'added' => $eligible->count(), 'skipped' => $skipped];
        });
    }

    /** Motivo por el que un envío NO puede entrar a un lote, o null si sí puede. */
    public function rejectionReason(ShippingRequest $shipment): ?string
    {
        if ($shipment->status === ShippingRequest::STATUS_ANULADO) {
            return 'Está anulado.';
        }

        if ($shipment->is_pickup) {
            return 'Es recojo en tienda: no genera rótulo de transporte.';
        }

        // Ya salió de la tienda (despachado, en agencia, en ruta, entregado): su
        // rótulo ya cumplió. Volver a meterlo a un lote duplica etiquetas.
        if (in_array($shipment->status, ShippingRequest::LABEL_LOCKED_STATUSES, true)) {
            return 'Ya figura como "' . $shipment->status_label . '": no entra a un lote nuevo.';
        }

        if ($shipment->print_batch_id) {
            $batch = $shipment->printBatch;

            return $batch && $batch->isPrinted()
                ? "Ya pertenece al lote impreso {$batch->code}."
                : 'Ya pertenece a un lote abierto (' . ($batch->code ?? '—') . ').';
        }

        if (ShippingSetting::current()->require_payment && !$shipment->payment_confirmed) {
            return 'Falta confirmar su pago.';
        }

        return null;
    }

    /** Agrega envíos a un lote que sigue abierto. */
    public function addToBatch(ShippingPrintBatch $batch, array $ids): array
    {
        if (!$batch->isEditable()) {
            return ['added' => 0, 'skipped' => ['lote' => 'El lote ya fue impreso: no admite envíos nuevos.']];
        }

        $added   = 0;
        $skipped = [];

        foreach (ShippingRequest::whereIn('id', $ids)->get() as $shipment) {
            $reason = $this->rejectionReason($shipment);

            if ($reason) {
                $skipped[$shipment->shipment_code ?: $shipment->id] = $reason;
                continue;
            }

            $shipment->forceFill(['print_batch_id' => $batch->id])->save();
            $added++;

            ShippingAuditLog::log(
                ShippingAuditLog::ACTION_BATCH_ADD,
                $shipment->id,
                'print_batch_id',
                null,
                $batch->code,
                null,
                $batch->id
            );
        }

        $this->refreshTotals($batch);

        return ['added' => $added, 'skipped' => $skipped];
    }

    /** Retira un envío de un lote abierto (para poder cambiarle la modalidad). */
    public function removeFromBatch(ShippingRequest $shipment): array
    {
        $batch = $shipment->printBatch;

        if (!$batch) {
            return [false, 'El envío no pertenece a ningún lote.'];
        }

        if ($batch->isPrinted()) {
            return [false, "El lote {$batch->code} ya fue impreso. Genera un lote nuevo para reprocesar este envío."];
        }

        $shipment->forceFill(['print_batch_id' => null])->save();

        ShippingAuditLog::log(
            ShippingAuditLog::ACTION_BATCH_PULL,
            $shipment->id,
            'print_batch_id',
            $batch->code,
            null,
            null,
            $batch->id
        );

        $this->refreshTotals($batch);

        return [true, "Envío retirado del lote {$batch->code}."];
    }

    /**
     * Marca el lote como IMPRESO: a partir de aquí sus envíos quedan
     * bloqueados y no se pueden mezclar con pedidos nuevos.
     */
    public function markPrinted(ShippingPrintBatch $batch, int $sheetCount, ?string $format = null): ShippingPrintBatch
    {
        return DB::connection('tenant')->transaction(function () use ($batch, $sheetCount, $format) {
            $user      = auth()->user();
            $shipments = $batch->shipments()->get();

            $batch->update([
                'status'          => ShippingPrintBatch::STATUS_PRINTED,
                'printed_at'      => now(),
                'printed_by'      => $user?->id,
                'printed_by_name' => $user?->name,
                'label_count'     => $shipments->count(),
                'sheet_count'     => $sheetCount,
                'package_count'   => (int) $shipments->sum(fn ($s) => max(1, (int) $s->package_count)),
                'label_range'     => $batch->computeLabelRange(),
                'format'          => $format ?: $batch->format,
                'manifest_code'   => $batch->manifest_code
                    ?: ($batch->generatesManifest() ? ShippingPrintBatch::nextManifestCode() : null),
            ]);

            foreach ($shipments as $shipment) {
                $before = $shipment->status;

                $shipment->forceFill([
                    'printed_at'  => now(),
                    'print_count' => (int) $shipment->print_count + 1,
                ]);

                // El rótulo impreso mueve el estado, salvo que el envío ya
                // vaya más adelante en su flujo (no se retrocede).
                if ($this->shouldAdvanceToPrinted($shipment)) {
                    $shipment->status = ShippingRequest::STATUS_IMPRESO;
                }

                $shipment->save();

                if ($before !== $shipment->status) {
                    ShippingAuditLog::log(
                        ShippingAuditLog::ACTION_STATUS,
                        $shipment->id,
                        'status',
                        $before,
                        $shipment->status,
                        'Automático por impresión del lote ' . $batch->code,
                        $batch->id
                    );
                }
            }

            ShippingPrintEvent::record($batch->id, null, $shipments->count(), $format ?: $batch->format);

            ShippingAuditLog::log(
                ShippingAuditLog::ACTION_PRINT,
                null,
                'status',
                ShippingPrintBatch::STATUS_OPEN,
                ShippingPrintBatch::STATUS_PRINTED,
                "{$shipments->count()} etiqueta(s) · {$sheetCount} hoja(s)",
                $batch->id
            );

            return $batch->fresh();
        });
    }

    /** Registra una reimpresión (exige motivo) sin tocar el historial previo. */
    public function registerReprint(ShippingPrintBatch $batch, string $reason, ?string $format = null): ShippingPrintEvent
    {
        $event = ShippingPrintEvent::record(
            $batch->id,
            null,
            $batch->shipments()->count(),
            $format ?: $batch->format,
            $reason
        );

        ShippingAuditLog::log(
            ShippingAuditLog::ACTION_REPRINT,
            null,
            'sequence',
            $event->sequence - 1,
            $event->sequence,
            $reason,
            $batch->id
        );

        return $event;
    }

    /** Cierra el lote: ya despachado, queda solo como historial. */
    public function closeBatch(ShippingPrintBatch $batch): array
    {
        if ($batch->status === ShippingPrintBatch::STATUS_CLOSED) {
            return [false, 'El lote ya estaba cerrado.'];
        }

        if (!$batch->isPrinted()) {
            return [false, 'Solo se pueden cerrar lotes ya impresos.'];
        }

        $user = auth()->user();

        $batch->update([
            'status'         => ShippingPrintBatch::STATUS_CLOSED,
            'closed_at'      => now(),
            'closed_by'      => $user?->id,
            'closed_by_name' => $user?->name,
        ]);

        ShippingAuditLog::log(
            ShippingAuditLog::ACTION_BATCH_CLOSE,
            null,
            'status',
            ShippingPrintBatch::STATUS_PRINTED,
            ShippingPrintBatch::STATUS_CLOSED,
            null,
            $batch->id
        );

        return [true, "Lote {$batch->code} cerrado."];
    }

    /** Descarta un lote abierto y libera sus envíos. */
    public function discardBatch(ShippingPrintBatch $batch): array
    {
        if ($batch->isPrinted()) {
            return [false, 'Un lote impreso no se puede descartar; ciérralo cuando se despache.'];
        }

        DB::connection('tenant')->transaction(function () use ($batch) {
            foreach ($batch->shipments()->get() as $shipment) {
                $shipment->forceFill(['print_batch_id' => null])->save();

                ShippingAuditLog::log(
                    ShippingAuditLog::ACTION_BATCH_PULL,
                    $shipment->id,
                    'print_batch_id',
                    $batch->code,
                    null,
                    'Lote descartado',
                    $batch->id
                );
            }

            $batch->update(['status' => ShippingPrintBatch::STATUS_CANCELLED]);
        });

        return [true, "Lote {$batch->code} descartado; sus envíos volvieron a la cola."];
    }

    // ── Corte operativo ────────────────────────────────────────────────────

    /**
     * Ventana del lote del día según la hora de corte configurada.
     *
     * Con corte a las 18:00, a las 19:30 la ventana vigente es
     * [hoy 18:00 → mañana 18:00): lo registrado después del corte queda para
     * el lote siguiente aunque sea el mismo día calendario.
     *
     * @return array{from: \Carbon\Carbon, to: \Carbon\Carbon, cutoff: string|null}
     */
    public function currentWindow(): array
    {
        $cutoff = ShippingSetting::current()->cutoff_time;

        if (!$cutoff) {
            // Sin corte configurado, la ventana es el día calendario.
            return ['from' => now()->startOfDay(), 'to' => now()->endOfDay(), 'cutoff' => null];
        }

        [$h, $m] = array_pad(explode(':', (string) $cutoff), 2, 0);

        $todayCutoff = now()->setTime((int) $h, (int) $m, 0);

        return now()->lt($todayCutoff)
            ? ['from' => $todayCutoff->copy()->subDay(), 'to' => $todayCutoff, 'cutoff' => (string) $cutoff]
            : ['from' => $todayCutoff, 'to' => $todayCutoff->copy()->addDay(), 'cutoff' => (string) $cutoff];
    }

    /**
     * Envíos listos para imprimir dentro de la ventana de corte vigente.
     * Los posteriores al corte quedan para el lote siguiente.
     */
    public function readyForCurrentBatch(): Collection
    {
        $window = $this->currentWindow();

        return ShippingRequest::query()
            ->whereNull('print_batch_id')
            ->whereNotIn('status', ShippingRequest::LABEL_LOCKED_STATUSES)
            ->where('delivery_type', '!=', ShippingRequest::DELIVERY_TIENDA)
            ->where('created_at', '<', $window['to'])
            ->orderBy('priority')
            ->orderBy('created_at')
            ->get();
    }

    /** Envíos que quedaron fuera del corte y esperan el lote siguiente. */
    public function waitingNextBatch(): Collection
    {
        $window = $this->currentWindow();

        if (!$window['cutoff']) {
            return collect();
        }

        return ShippingRequest::query()
            ->whereNull('print_batch_id')
            ->whereNotIn('status', ShippingRequest::LABEL_LOCKED_STATUSES)
            ->where('delivery_type', '!=', ShippingRequest::DELIVERY_TIENDA)
            ->where('created_at', '>=', $window['to'])
            ->orderBy('created_at')
            ->get();
    }

    // ── Internos ───────────────────────────────────────────────────────────

    /** Recalcula contadores del lote tras agregar o quitar envíos. */
    private function refreshTotals(ShippingPrintBatch $batch): void
    {
        $shipments = $batch->shipments()->get();

        $batch->update([
            'label_count'   => $shipments->count(),
            'package_count' => (int) $shipments->sum(fn ($s) => max(1, (int) $s->package_count)),
            'label_range'   => $batch->computeLabelRange(),
            'delivery_type' => $this->dominantType($shipments),
        ]);
    }

    /** Modalidad del lote: la común a todos, o null si es mixto. */
    private function dominantType(Collection $shipments): ?string
    {
        $types = $shipments->pluck('delivery_type')->unique()->values();

        return $types->count() === 1 ? $types->first() : null;
    }

    /** ¿El envío debe avanzar a "impreso"? No se retrocede el flujo. */
    private function shouldAdvanceToPrinted(ShippingRequest $shipment): bool
    {
        $flow     = ShippingRequest::statusOrderFor($shipment->delivery_type);
        $current  = array_search($shipment->status, $flow, true);
        $printed  = array_search(ShippingRequest::STATUS_IMPRESO, $flow, true);

        if ($printed === false) {
            return false;
        }

        // Estado legado o desconocido: se considera anterior a la impresión.
        return $current === false || $current < $printed;
    }
}
