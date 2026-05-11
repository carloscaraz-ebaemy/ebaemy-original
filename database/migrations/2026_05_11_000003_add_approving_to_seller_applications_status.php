<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el estado intermedio 'approving' al enum de seller_applications.status.
 *
 * Lo seteamos cuando el SuperAdmin clickea "Aprobar y crear tenant" — el
 * tenant se crea en background (ProcessSellerApprovalJob via
 * dispatchAfterResponse) para evitar el timeout de nginx (30s) durante el
 * migrate masivo. Mientras tanto, la UI muestra estado 'approving'.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('seller_applications')) return;

        // ALTER TABLE para extender el enum. MySQL no permite agregar valores
        // sin redefinir el enum entero — listamos todos los actuales + el nuevo.
        DB::statement("
            ALTER TABLE seller_applications
            MODIFY COLUMN status ENUM(
                'pending',
                'under_review',
                'requires_documents',
                'requires_review',
                'approving',
                'approved',
                'rejected',
                'cancelled'
            ) NOT NULL DEFAULT 'pending'
        ");
    }

    public function down(): void
    {
        if (!Schema::hasTable('seller_applications')) return;

        // Si hay filas en 'approving' al hacer rollback, las regresamos a
        // 'under_review' para no perder datos.
        DB::table('seller_applications')->where('status', 'approving')->update(['status' => 'under_review']);

        DB::statement("
            ALTER TABLE seller_applications
            MODIFY COLUMN status ENUM(
                'pending',
                'under_review',
                'requires_documents',
                'requires_review',
                'approved',
                'rejected',
                'cancelled'
            ) NOT NULL DEFAULT 'pending'
        ");
    }
};
