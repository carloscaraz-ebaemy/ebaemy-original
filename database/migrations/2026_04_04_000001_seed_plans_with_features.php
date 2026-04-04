<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crea los 5 planes con sus features asignados.
 *
 * Planes:
 *   1. Tienda Web   (S/ 29)  — Solo ecommerce, sin facturación
 *   2. Caserito      (S/ 49)  — Facturación básica, POS
 *   3. Negocio       (S/ 99)  — ERP + Ecommerce + Variantes
 *   4. Pro           (S/ 179) — Todo + Smart Stock, Logístico, Flash Sales, Reportes
 *   5. Enterprise    (S/ 299) — Todo ilimitado + WhatsApp API
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Crear planes ──────────────────────────────────────────────────

        $plans = [
            [
                'name'                  => 'Tienda Web',
                'pricing'               => 29,
                'limit_users'           => 1,
                'limit_documents'       => 0,
                'establishments_limit'  => 1,
                'establishments_unlimited' => false,
                'sales_limit'           => 0,
                'sales_unlimited'       => true,
                'locked'                => false,
                'include_sale_notes_sales_limit' => false,
                'include_sale_notes_limit_documents' => false,
            ],
            [
                'name'                  => 'Caserito',
                'pricing'               => 49,
                'limit_users'           => 1,
                'limit_documents'       => 0,
                'establishments_limit'  => 1,
                'establishments_unlimited' => false,
                'sales_limit'           => 0,
                'sales_unlimited'       => true,
                'locked'                => false,
                'include_sale_notes_sales_limit' => false,
                'include_sale_notes_limit_documents' => false,
            ],
            [
                'name'                  => 'Negocio',
                'pricing'               => 99,
                'limit_users'           => 3,
                'limit_documents'       => 0,
                'establishments_limit'  => 2,
                'establishments_unlimited' => false,
                'sales_limit'           => 0,
                'sales_unlimited'       => true,
                'locked'                => false,
                'include_sale_notes_sales_limit' => false,
                'include_sale_notes_limit_documents' => false,
            ],
            [
                'name'                  => 'Pro',
                'pricing'               => 179,
                'limit_users'           => 5,
                'limit_documents'       => 0,
                'establishments_limit'  => 3,
                'establishments_unlimited' => false,
                'sales_limit'           => 0,
                'sales_unlimited'       => true,
                'locked'                => false,
                'include_sale_notes_sales_limit' => false,
                'include_sale_notes_limit_documents' => false,
            ],
            [
                'name'                  => 'Enterprise',
                'pricing'               => 299,
                'limit_users'           => 0, // 0 = ilimitados
                'limit_documents'       => 0,
                'establishments_limit'  => 0,
                'establishments_unlimited' => true,
                'sales_limit'           => 0,
                'sales_unlimited'       => true,
                'locked'                => false,
                'include_sale_notes_sales_limit' => false,
                'include_sale_notes_limit_documents' => false,
            ],
        ];

        foreach ($plans as $planData) {
            DB::table('plans')->updateOrInsert(
                ['name' => $planData['name']],
                array_merge($planData, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        // ── 2. Asignar features a cada plan ─────────────────────────────────

        // Mapeo: plan_name => [feature_keys]
        $planFeatures = [
            'Tienda Web' => [
                'ecommerce',
                'promotions',       // cupones básicos
            ],
            'Caserito' => [
                // Solo facturación y POS — sin features adicionales
            ],
            'Negocio' => [
                'ecommerce',
                'variants',
                'promotions',
            ],
            'Pro' => [
                'ecommerce',
                'logistic_module',
                'smart_stock',
                'variants',
                'promotions',
                'flash_sales',
                'advanced_reports',
                'multi_establishment',
                'google_login',
                'culqi_preauth',
            ],
            'Enterprise' => [
                'ecommerce',
                'logistic_module',
                'smart_stock',
                'variants',
                'promotions',
                'flash_sales',
                'carrier_api',
                'culqi_preauth',
                'whatsapp_api',
                'google_login',
                'advanced_reports',
                'multi_establishment',
                'read_replica',
            ],
        ];

        // Cargar IDs
        $featureIds = DB::table('features')->pluck('id', 'key')->toArray();
        $planIds    = DB::table('plans')->pluck('id', 'name')->toArray();

        foreach ($planFeatures as $planName => $featureKeys) {
            $planId = $planIds[$planName] ?? null;
            if (!$planId) continue;

            foreach ($featureKeys as $featureKey) {
                $featureId = $featureIds[$featureKey] ?? null;
                if (!$featureId) continue;

                DB::table('plan_features')->updateOrInsert(
                    ['plan_id' => $planId, 'feature_id' => $featureId],
                    ['limit' => null, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        $planNames = ['Tienda Web', 'Caserito', 'Negocio', 'Pro', 'Enterprise'];
        $planIds = DB::table('plans')->whereIn('name', $planNames)->pluck('id');

        DB::table('plan_features')->whereIn('plan_id', $planIds)->delete();
        DB::table('plans')->whereIn('name', $planNames)->delete();
    }
};
