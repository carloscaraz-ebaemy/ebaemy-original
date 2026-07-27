<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arquitectura logística del módulo de Envíos: lotes de impresión,
 * historial de impresiones/reimpresiones y auditoría.
 *
 * Separación de responsabilidades (recomendación del rediseño):
 *   delivery_type   → MODALIDAD  (provincia / lima / recojo en tienda)
 *   status          → ESTADO logístico (pendiente … entregado / anulado)
 *   print_batch_id  → LOTE de impresión (sin lote, o LOTE-000125)
 *
 * Así cambiar la modalidad no toca el historial logístico ni el lote.
 *
 * NOTA sobre los valores de `delivery_type`: se conservan los existentes
 * ('domicilio' = Lima/Callao, 'agencia' = Provincia) y solo se añade
 * 'tienda'. Renombrarlos habría obligado a reescribir rótulos, flujos de
 * estado, seguimiento público y QR de 76 envíos vivos sin ganar nada:
 * lo que cambia es la etiqueta, no el significado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shipping_requests')) {
            return;   // el tenant no tiene el módulo de Envíos
        }

        // ── Lotes de impresión ──────────────────────────────────────────
        if (!Schema::hasTable('shipping_print_batches')) {
            Schema::create('shipping_print_batches', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique();              // LOTE-000125
                $table->string('manifest_code', 20)->nullable();   // MAN-000125
                $table->string('status', 20)->default('open')->index(); // open|printed|closed|cancelled
                $table->string('delivery_type', 20)->nullable()->index(); // null = mixto

                $table->unsignedSmallInteger('label_count')->default(0);
                $table->unsignedSmallInteger('sheet_count')->default(0);
                $table->unsignedSmallInteger('package_count')->default(0);
                $table->string('label_range', 60)->nullable();     // rango de etiquetas impresas
                $table->string('format', 10)->nullable();          // sticker | a5 | a4

                $table->dateTime('printed_at')->nullable();
                $table->unsignedInteger('printed_by')->nullable();
                $table->string('printed_by_name', 120)->nullable();
                $table->dateTime('closed_at')->nullable();
                $table->unsignedInteger('closed_by')->nullable();
                $table->string('closed_by_name', 120)->nullable();

                $table->unsignedInteger('created_by')->nullable();
                $table->string('created_by_name', 120)->nullable();
                $table->string('notes', 255)->nullable();
                $table->timestamps();
                $table->index('printed_at');
            });
        }

        // ── Impresiones y reimpresiones ─────────────────────────────────
        // sequence = 1 → impresión original; > 1 → reimpresión (exige motivo).
        // Nunca se sobrescribe: cada impresión agrega una fila.
        if (!Schema::hasTable('shipping_print_events')) {
            Schema::create('shipping_print_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('batch_id')->nullable()->index();
                $table->unsignedBigInteger('shipment_id')->nullable()->index();
                $table->unsignedSmallInteger('sequence')->default(1);
                $table->boolean('is_reprint')->default(false)->index();
                $table->string('reason', 255)->nullable();
                $table->unsignedSmallInteger('label_count')->default(0);
                $table->string('format', 10)->nullable();
                $table->unsignedInteger('user_id')->nullable();
                $table->string('user_name', 120)->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamps();
            });
        }

        // ── Auditoría ───────────────────────────────────────────────────
        if (!Schema::hasTable('shipping_audit_logs')) {
            Schema::create('shipping_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('shipment_id')->nullable()->index();
                $table->unsignedBigInteger('batch_id')->nullable()->index();
                $table->string('action', 40)->index();     // modality_change, print, reprint…
                $table->string('field', 40)->nullable();
                $table->string('old_value', 255)->nullable();
                $table->string('new_value', 255)->nullable();
                $table->string('notes', 500)->nullable();
                $table->boolean('is_exception')->default(false)->index();
                $table->unsignedInteger('user_id')->nullable();
                $table->string('user_name', 120)->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamps();
                $table->index('created_at');
            });
        }

        // ── Columnas nuevas en shipping_requests ────────────────────────
        Schema::table('shipping_requests', function (Blueprint $table) {
            $has = fn ($c) => Schema::hasColumn('shipping_requests', $c);

            if (!$has('print_batch_id'))  $table->unsignedBigInteger('print_batch_id')->nullable()->index()->after('status');
            if (!$has('priority'))        $table->unsignedTinyInteger('priority')->default(3)->index()->after('print_batch_id');
            if (!$has('printed_at'))      $table->dateTime('printed_at')->nullable()->after('priority');
            if (!$has('print_count'))     $table->unsignedSmallInteger('print_count')->default(0)->after('printed_at');
            if (!$has('ready_at'))        $table->dateTime('ready_at')->nullable()->after('print_count');

            // Anulación (nunca se borra el registro).
            if (!$has('cancelled_at'))      $table->dateTime('cancelled_at')->nullable()->after('ready_at');
            if (!$has('cancelled_by'))      $table->unsignedInteger('cancelled_by')->nullable()->after('cancelled_at');
            if (!$has('cancelled_by_name')) $table->string('cancelled_by_name', 120)->nullable()->after('cancelled_by');
            if (!$has('cancel_reason'))     $table->string('cancel_reason', 255)->nullable()->after('cancelled_by_name');
            if (!$has('status_before_cancel')) $table->string('status_before_cancel', 30)->nullable()->after('cancel_reason');

            // Restauración.
            if (!$has('restored_at'))      $table->dateTime('restored_at')->nullable()->after('status_before_cancel');
            if (!$has('restored_by'))      $table->unsignedInteger('restored_by')->nullable()->after('restored_at');
            if (!$has('restored_by_name')) $table->string('restored_by_name', 120)->nullable()->after('restored_by');
            if (!$has('restore_reason'))   $table->string('restore_reason', 255)->nullable()->after('restored_by_name');

            // Recojo en tienda.
            if (!$has('picked_up_at'))     $table->dateTime('picked_up_at')->nullable()->after('restore_reason');
            if (!$has('picked_up_by'))     $table->string('picked_up_by', 160)->nullable()->after('picked_up_at');
        });

        // Hora de corte operativo.
        if (Schema::hasTable('shipping_settings') && !Schema::hasColumn('shipping_settings', 'cutoff_time')) {
            Schema::table('shipping_settings', function (Blueprint $table) {
                $table->time('cutoff_time')->nullable();
            });
        }

        $this->backfillPriority();
    }

    /** Asigna la prioridad logística a los envíos existentes según su modalidad. */
    private function backfillPriority(): void
    {
        if (!Schema::hasColumn('shipping_requests', 'priority')) {
            return;
        }

        $map = [
            'domicilio' => 1,   // Lima/Callao — mismo día o al día siguiente
            'tienda'    => 2,   // Recojo en tienda — preparar cuanto antes
            'agencia'   => 3,   // Provincia — dentro del plazo operativo
        ];

        foreach ($map as $type => $priority) {
            \Illuminate\Support\Facades\DB::table('shipping_requests')
                ->where('delivery_type', $type)
                ->update(['priority' => $priority]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_audit_logs');
        Schema::dropIfExists('shipping_print_events');
        Schema::dropIfExists('shipping_print_batches');

        if (Schema::hasTable('shipping_requests')) {
            Schema::table('shipping_requests', function (Blueprint $table) {
                foreach ([
                    'print_batch_id', 'priority', 'printed_at', 'print_count', 'ready_at',
                    'cancelled_at', 'cancelled_by', 'cancelled_by_name', 'cancel_reason',
                    'status_before_cancel', 'restored_at', 'restored_by', 'restored_by_name',
                    'restore_reason', 'picked_up_at', 'picked_up_by',
                ] as $column) {
                    if (Schema::hasColumn('shipping_requests', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
