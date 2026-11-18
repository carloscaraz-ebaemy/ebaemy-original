<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quita de «Qué buscan tus clientes» las búsquedas con llaves, sobre todo
 * `{search_term_string}`: el marcador del SearchAction de schema.org que
 * algunos robots piden literal. Eran 29 de las 87 búsquedas sin resultados
 * de un mes y tapaban las de verdad. Desde 2026-11-18 SearchInsights::record
 * ya no las registra (SearchInsights::isNoise); esto limpia las de antes.
 *
 * Idempotente: un DELETE con el mismo criterio; correrlo otra vez no borra
 * nada más. No tiene vuelta atrás porque lo borrado es ruido.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_search_stats_daily')) {
            return;
        }

        DB::table('marketplace_search_stats_daily')
            ->where(function ($q) {
                $q->where('term_norm', 'like', '%{%')
                  ->orWhere('term_norm', 'like', '%}%')
                  ->orWhere('term_sample', 'like', '%{%')
                  ->orWhere('term_sample', 'like', '%}%');
            })
            ->delete();
    }

    public function down(): void
    {
        // Nada que restaurar: eran búsquedas de robots.
    }
};
