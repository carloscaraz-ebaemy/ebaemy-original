<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué buscan los compradores en el marketplace, y qué buscan sin encontrar.
 *
 * Hasta ahora el término buscado sólo vivía en la sesión del comprador (las 6
 * últimas, para recomendarle cosas) y se perdía al cerrar el navegador. Nadie
 * podía responder la pregunta que de verdad importa: qué escribe la gente que
 * devuelve cero resultados. Sin eso, cada mejora del buscador es una
 * corazonada, y encima se pierde el mejor argumento para captar tiendas
 * nuevas: «esto lo buscan N veces al mes y nadie lo vende».
 *
 * Agregado por (término, día) y no una fila por búsqueda, igual que
 * `marketplace_listing_stats_daily`: con INSERT ... ON DUPLICATE KEY UPDATE no
 * hay carrera aunque dos visitantes busquen lo mismo a la vez, y la tabla
 * crece con los términos distintos, no con el tráfico.
 *
 * No guarda IP, ni usuario, ni sesión: para decidir qué catálogo falta basta
 * el término y cuántas veces se buscó. Un término de búsqueda puede llevar un
 * nombre o un teléfono escrito por error, así que cuanto menos se guarde
 * alrededor, mejor.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketplace_search_stats_daily')) {
            return;
        }

        Schema::create('marketplace_search_stats_daily', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');

            // Normalizado (minúsculas, sin tildes, espacios colapsados) para
            // que «Zapatillas», «zapatillas» y «zapatíllas» sean la misma fila.
            $table->string('term_norm', 120);

            // Cómo lo escribió alguien de verdad. Es lo que se enseña en el
            // panel: leer «audifonos bluetooth» dice más que su normalización.
            $table->string('term_sample', 160)->nullable();

            $table->unsignedInteger('searches')->default(0);
            $table->unsignedInteger('zero_results')->default(0);

            // Resultados de la última búsqueda del día con ese término. Sirve
            // para ver si un término pasó de 0 a algo tras publicar catálogo.
            $table->unsignedInteger('last_results')->default(0);

            $table->timestamps();

            $table->unique(['stat_date', 'term_norm'], 'mp_search_day_term_uniq');
            // El panel ordena por estas dos dentro de un rango de fechas.
            $table->index(['stat_date', 'zero_results'], 'mp_search_zero_idx');
            $table->index(['stat_date', 'searches'], 'mp_search_top_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_search_stats_daily');
    }
};
