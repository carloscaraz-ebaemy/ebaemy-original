<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plan Caserito: publicación en el marketplace SIN límite.
 *
 * La migración 2026_04_24_200001 dio `marketplace_products_limit` ilimitado
 * solo a los planes que ya traían `ecommerce`; Caserito no lo traía y se quedó
 * sin fila. FeatureGate::limit() lee «sin fila» como 0 = «tu plan no incluye
 * el marketplace», así que una tienda de pago (S/ 49) podía publicar MENOS
 * que el plan Gratis (25). Caso real: Importaciones Deywa, 361 productos en
 * su tienda y 0 en el marketplace. Decisión del 2026-10-24: sin límite, igual
 * que Tienda Web, Negocio, Pro y Enterprise.
 *
 * Idempotente: updateOrInsert sobre (plan_id, feature_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plan_features')) {
            return;
        }

        $planId    = DB::table('plans')->where('name', 'Caserito')->value('id');
        $featureId = DB::table('features')->where('key', 'marketplace_products_limit')->value('id');
        if (!$planId || !$featureId) {
            return;
        }

        DB::table('plan_features')->updateOrInsert(
            ['plan_id' => $planId, 'feature_id' => $featureId],
            ['limit' => null, 'meta' => null, 'created_at' => now(), 'updated_at' => now()]
        );

        $this->olvidarCacheDeFeatures($planId);
    }

    public function down(): void
    {
        $planId    = DB::table('plans')->where('name', 'Caserito')->value('id');
        $featureId = DB::table('features')->where('key', 'marketplace_products_limit')->value('id');
        if ($planId && $featureId) {
            DB::table('plan_features')->where(['plan_id' => $planId, 'feature_id' => $featureId])->delete();
            $this->olvidarCacheDeFeatures($planId);
        }
    }

    /**
     * FeatureGate guarda las features 10 min por hostname; sin esto la tienda
     * seguiría viendo «tu plan no incluye el marketplace» hasta que caduque.
     */
    private function olvidarCacheDeFeatures(int $planId): void
    {
        try {
            $hostnames = DB::table('clients')->where('plan_id', $planId)->pluck('hostname_id');
            foreach ($hostnames as $id) {
                Cache::forget("feature_gate_{$id}");
            }
        } catch (\Throwable $e) {
            // Sin caché que limpiar: caduca sola en 10 minutos.
        }
    }
};
