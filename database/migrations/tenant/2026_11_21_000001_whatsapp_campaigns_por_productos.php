<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campañas de WhatsApp por PRODUCTOS (no solo por Flash Sale), con envío
 * espaciado por cola y baja del cliente.
 *
 *  - whatsapp_offer_campaigns.kind: flash_sale | products
 *  - whatsapp_offer_campaign_messages.scheduled_at: cuándo está programado
 *    el envío (la cola lo manda uno cada N segundos, con tope diario).
 *  - persons.whatsapp_opt_out: el cliente pidió no recibir más avisos.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('whatsapp_offer_campaigns') && !Schema::hasColumn('whatsapp_offer_campaigns', 'kind')) {
            Schema::table('whatsapp_offer_campaigns', function (Blueprint $table) {
                $table->string('kind', 20)->default('flash_sale')->after('name');
            });
        }

        if (Schema::hasTable('whatsapp_offer_campaign_messages') && !Schema::hasColumn('whatsapp_offer_campaign_messages', 'scheduled_at')) {
            Schema::table('whatsapp_offer_campaign_messages', function (Blueprint $table) {
                $table->dateTime('scheduled_at')->nullable()->after('sent_at');
                $table->index(['status', 'scheduled_at']);
            });
        }

        if (Schema::hasTable('persons') && !Schema::hasColumn('persons', 'whatsapp_opt_out')) {
            Schema::table('persons', function (Blueprint $table) {
                $table->boolean('whatsapp_opt_out')->default(false);
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('whatsapp_offer_campaigns', 'kind')) {
            Schema::table('whatsapp_offer_campaigns', fn (Blueprint $t) => $t->dropColumn('kind'));
        }
        if (Schema::hasColumn('whatsapp_offer_campaign_messages', 'scheduled_at')) {
            Schema::table('whatsapp_offer_campaign_messages', function (Blueprint $t) {
                $t->dropIndex(['status', 'scheduled_at']);
                $t->dropColumn('scheduled_at');
            });
        }
        if (Schema::hasColumn('persons', 'whatsapp_opt_out')) {
            Schema::table('persons', fn (Blueprint $t) => $t->dropColumn('whatsapp_opt_out'));
        }
    }
};
