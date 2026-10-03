<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atribución de pedidos y leads del marketplace a la campaña que los trajo.
 *
 * El UTM que ya existía (MarketplaceListing::tenant_item_url_with_utm) etiqueta
 * el clic que SALE del marketplace hacia la tienda del tenant. Esto es lo
 * contrario: guarda de dónde VINO el visitante, para poder contestar cuánto se
 * vendió por campaña sin depender del informe de la plataforma.
 *
 * click_id_fb / click_id_tt no son adorno: son `fbclid` y `ttclid`, y son lo
 * que la API de conversiones usa para casar la venta con el anuncio concreto.
 * Sin ellos Meta y TikTok sólo pueden casar por email/teléfono hasheado, con
 * bastante menos acierto.
 *
 * ads_purchase_sent_at es la guarda de idempotencia del evento Purchase
 * server-side: hay tres caminos que llegan a "pedido pagado" (contra entrega,
 * retorno de MercadoPago y webhook) y un Purchase duplicado infla el ROAS de
 * la cuenta publicitaria sin posibilidad de corregirlo después.
 */
return new class extends Migration {
    /** Columnas de origen, iguales en pedidos y leads. */
    private function attributionColumns(Blueprint $table, string $after): void
    {
        $table->string('utm_source', 120)->nullable()->after($after);
        $table->string('utm_medium', 120)->nullable()->after('utm_source');
        $table->string('utm_campaign', 180)->nullable()->after('utm_medium');
        $table->string('utm_content', 180)->nullable()->after('utm_campaign');
        $table->string('utm_term', 180)->nullable()->after('utm_content');
        $table->string('click_id_fb', 255)->nullable()->after('utm_term');
        $table->string('click_id_tt', 255)->nullable()->after('click_id_fb');
        $table->string('click_id_google', 255)->nullable()->after('click_id_tt');
    }

    public function up(): void
    {
        if (Schema::hasTable('marketplace_orders')) {
            Schema::table('marketplace_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('marketplace_orders', 'utm_source')) {
                    $this->attributionColumns($table, 'created_at');
                    // El informe por campaña agrupa por aquí.
                    $table->index('utm_campaign', 'mp_orders_utm_campaign_idx');
                }
                if (!Schema::hasColumn('marketplace_orders', 'ads_purchase_sent_at')) {
                    $table->timestamp('ads_purchase_sent_at')->nullable()
                          ->after('click_id_google');
                }
            });
        }

        if (Schema::hasTable('marketplace_leads')) {
            Schema::table('marketplace_leads', function (Blueprint $table) {
                if (!Schema::hasColumn('marketplace_leads', 'utm_source')) {
                    $this->attributionColumns($table, 'source_ua');
                    $table->index('utm_campaign', 'mp_leads_utm_campaign_idx');
                }
            });
        }
    }

    public function down(): void
    {
        $cols = [
            'click_id_google', 'click_id_tt', 'click_id_fb',
            'utm_term', 'utm_content', 'utm_campaign', 'utm_medium', 'utm_source',
        ];

        if (Schema::hasTable('marketplace_orders')) {
            Schema::table('marketplace_orders', function (Blueprint $table) use ($cols) {
                if (Schema::hasColumn('marketplace_orders', 'utm_campaign')) {
                    $table->dropIndex('mp_orders_utm_campaign_idx');
                }
                foreach (array_merge(['ads_purchase_sent_at'], $cols) as $col) {
                    if (Schema::hasColumn('marketplace_orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('marketplace_leads')) {
            Schema::table('marketplace_leads', function (Blueprint $table) use ($cols) {
                if (Schema::hasColumn('marketplace_leads', 'utm_campaign')) {
                    $table->dropIndex('mp_leads_utm_campaign_idx');
                }
                foreach ($cols as $col) {
                    if (Schema::hasColumn('marketplace_leads', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
