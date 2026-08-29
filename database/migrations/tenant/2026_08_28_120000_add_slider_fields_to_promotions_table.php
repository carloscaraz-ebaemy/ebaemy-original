<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos del slider administrable (fase 3 del theme system).
 *
 * `promotions` solo tenía imagen + destino: el banner no podía llevar texto,
 * no tenía versión mobile, no se podía ordenar ni programar. El home_slider
 * mostraba las imágenes en el orden que devolvía la query.
 *
 * Todas las columnas son ANULABLES y sin valor por defecto que cambie el
 * render: un banner existente sigue viéndose exactamente igual hasta que
 * alguien le llene los campos nuevos.
 *
 * FK a `categories` como unsignedInteger, no foreignId(): las tablas legacy
 * del tenant usan increments() y un bigInteger no matchea.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('promotions')) {
            return;
        }

        Schema::table('promotions', function (Blueprint $table) {
            if (!Schema::hasColumn('promotions', 'image_mobile')) {
                // Imagen vertical para celular. Sin ella el slider usa la de
                // desktop, que es el comportamiento de siempre.
                $table->string('image_mobile')->nullable()->after('image');
            }
            if (!Schema::hasColumn('promotions', 'title')) {
                $table->string('title')->nullable()->after('image_mobile');
            }
            if (!Schema::hasColumn('promotions', 'subtitle')) {
                $table->string('subtitle', 500)->nullable()->after('title');
            }
            if (!Schema::hasColumn('promotions', 'button_text')) {
                $table->string('button_text', 60)->nullable()->after('subtitle');
            }
            if (!Schema::hasColumn('promotions', 'link_type')) {
                // product | url | category | none. Nulo = se deduce de
                // item_id/banner_url como venía haciendo el blade.
                $table->string('link_type', 20)->nullable()->after('button_text');
            }
            if (!Schema::hasColumn('promotions', 'link_category_id')) {
                $table->unsignedInteger('link_category_id')->nullable()->after('link_type');
            }
            if (!Schema::hasColumn('promotions', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('link_category_id');
            }
            if (!Schema::hasColumn('promotions', 'starts_at')) {
                $table->dateTime('starts_at')->nullable()->after('sort_order');
            }
            if (!Schema::hasColumn('promotions', 'ends_at')) {
                $table->dateTime('ends_at')->nullable()->after('starts_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('promotions')) {
            return;
        }

        Schema::table('promotions', function (Blueprint $table) {
            foreach ([
                'image_mobile', 'title', 'subtitle', 'button_text',
                'link_type', 'link_category_id', 'sort_order',
                'starts_at', 'ends_at',
            ] as $column) {
                if (Schema::hasColumn('promotions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
