<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buzon de vistas y clicks del marketplace — solo se anade, nunca se actualiza.
 *
 * PROBLEMA QUE RESUELVE
 * ---------------------
 * Cada ficha de producto hacia DOS escrituras sincronas antes de pintar nada:
 * `UPDATE marketplace_listings SET view_count = view_count + 1` y un upsert en
 * `marketplace_listing_stats_daily`. Las dos caen sobre LA MISMA fila, asi que
 * un producto en portada con 2.000 visitas por minuto son 2.000 UPDATE que
 * MySQL tiene que serializar: la cola de locks crece, el resto de peticiones
 * espera detras y acaba en `lock wait timeout`. Es el patron que no aguanta un
 * pico de trafico.
 *
 * Aqui cada visita es un INSERT nuevo. Dos filas distintas no se pelean por
 * ningun lock, asi que el coste no crece con lo popular que sea el producto.
 * `marketplace:flush-view-events` (cron cada minuto) agrupa lo acumulado y
 * aplica UN solo UPDATE por producto, dia y metrica.
 *
 * TRES COSAS QUE SON ASI A PROPOSITO
 * ----------------------------------
 *  - **Sin clave foranea a `marketplace_listings`.** Una FK obliga a MySQL a
 *    comprobar (y bloquear en modo compartido) la fila del padre en cada
 *    INSERT, que es justo el bloqueo del que estamos huyendo. La integridad la
 *    da el agregador, que descarta lo que apunte a un listing que ya no existe.
 *  - **Sin indices aparte de la clave primaria.** Cada indice es trabajo extra
 *    por INSERT y esta tabla se escribe en el camino caliente y se lee una vez
 *    por minuto. El agregador recorre un rango de `id`, que es la primaria.
 *  - **No se cachea un contador en su lugar** porque en este servidor
 *    `CACHE_DRIVER=file` y no hay redis instalado: los incrementos del driver
 *    de ficheros no son atomicos y con concurrencia alta se pierden cuentas.
 *    Si algun dia entra redis, esto se puede sustituir por un contador en
 *    memoria. Ver project_marketplace_carga_10k.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('system')->hasTable('marketplace_view_events')) return;

        Schema::connection('system')->create('marketplace_view_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('listing_id');
            $table->unsignedInteger('hostname_id')->nullable();
            // 'views' | 'clicks' — mismos nombres que las columnas de
            // marketplace_listing_stats_daily para que el agregador no traduzca.
            $table->string('metric', 10);
            // Marca el dia al que suma el evento. Sin `updated_at`: una fila
            // de estas nace y muere sin cambiar nunca.
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('system')->dropIfExists('marketplace_view_events');
    }
};
