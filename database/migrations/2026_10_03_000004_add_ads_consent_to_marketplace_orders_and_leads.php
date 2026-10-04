<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué decidió el comprador sobre la medición, guardado junto al pedido.
 *
 * Hasta ahora el consentimiento sólo vivía en la sesión, y eso dejaba el
 * banner a medias: el navegador no cargaba píxeles si el comprador pulsaba
 * «Rechazar», pero el envío server-side seguía mandando su email y su
 * teléfono hasheados a Meta y TikTok. Para la parte que más datos personales
 * mueve, el banner era decorativo.
 *
 * Tiene que estar en el pedido y no sólo en la sesión porque el webhook de
 * MercadoPago llega **sin sesión** del comprador: es la pasarela la que hace
 * esa petición. Sin este dato, ese camino no tiene forma de saber si puede
 * medir, y acabaría mandando lo que el comprador rechazó.
 *
 * Valores: 'granted', 'denied' o NULL cuando el comprador no llegó a decidir.
 * Sólo 'granted' habilita el envío.
 */
return new class extends Migration {
    public function up(): void
    {
        foreach (['marketplace_orders', 'marketplace_leads'] as $tabla) {
            if (!Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'ads_consent')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) {
                $table->string('ads_consent', 10)->nullable()->after('click_id_google');
            });
        }
    }

    public function down(): void
    {
        foreach (['marketplace_orders', 'marketplace_leads'] as $tabla) {
            if (!Schema::hasTable($tabla) || !Schema::hasColumn($tabla, 'ads_consent')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn('ads_consent');
            });
        }
    }
};
