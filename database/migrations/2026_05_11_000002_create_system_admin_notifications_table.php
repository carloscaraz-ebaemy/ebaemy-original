<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * system_admin_notifications — bandeja de notificaciones del SuperAdmin
 * de EBAEMY. Eventos que llegan aquí:
 *  - seller_registered: nuevo seller se registró/aprobó
 *  - marketplace_lead:  alguien envió formulario de info en un producto
 *  - marketplace_order: pedido nuevo en marketplace central
 *  - stock_critical:    producto destacado con stock bajo
 *  - (futuros)
 *
 * Cada fila se asocia opcionalmente a un recurso (clients, marketplace_listings,
 * etc.) vía related_type + related_id, para que el click navegue al detalle.
 *
 * is_read se actualiza cuando el SuperAdmin abre la notificación o la marca
 * como leída desde la campanita.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('system_admin_notifications')) return;

        Schema::create('system_admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);                       // 'seller_registered', etc.
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->string('icon', 20)->nullable();           // emoji o nombre de icono
            $table->string('link', 500)->nullable();          // URL al recurso
            $table->string('related_type', 80)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['is_read', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_admin_notifications');
    }
};
