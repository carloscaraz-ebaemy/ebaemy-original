<?php

namespace Database\Seeders;

use App\Models\System\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * PlanSeeder — Crea los 5 planes del sistema con sus features asignadas.
 *
 * Uso:
 *   php artisan db:seed --class=PlanSeeder
 *
 * Planes:
 *   1. Gratis       — POS básico, boletas, 1 usuario
 *   2. Emprendedor  — Facturación completa, ecommerce básico
 *   3. Negocio      — Smart stock, variantes, restaurant, multi-local
 *   4. Profesional  — Logístico, carriers, hotel, WhatsApp, reportes avanzados
 *   5. Enterprise   — Todo ilimitado + marketplace, replica, analytics
 *
 * SAFE: No duplica si ya existen (usa updateOrCreate).
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPlans();
        $this->seedPlanFeatures();
    }

    protected function seedPlans(): void
    {
        $plans = [
            [
                'name'                             => 'Gratis',
                'pricing'                          => 0,
                'limit_users'                      => 1,
                'limit_documents'                  => 30,
                'plan_documents'                   => [1],        // Solo boletas
                'locked'                           => false,
                'establishments_limit'             => 1,
                'establishments_unlimited'         => false,
                'sales_limit'                      => 5000,
                'sales_unlimited'                  => false,
                'include_sale_notes_sales_limit'   => true,
                'include_sale_notes_limit_documents'=> true,
            ],
            [
                'name'                             => 'Tienda Virtual',
                'pricing'                          => 29,
                'limit_users'                      => 2,
                'limit_documents'                  => 0,           // Sin facturación
                'plan_documents'                   => [],
                'locked'                           => false,
                'establishments_limit'             => 1,
                'establishments_unlimited'         => false,
                'sales_limit'                      => 0,
                'sales_unlimited'                  => true,
                'include_sale_notes_sales_limit'   => false,
                'include_sale_notes_limit_documents'=> false,
            ],
            [
                'name'                             => 'Emprendedor',
                'pricing'                          => 49,
                'limit_users'                      => 3,
                'limit_documents'                  => 150,
                'plan_documents'                   => [1, 2],     // Facturas + guías
                'locked'                           => false,
                'establishments_limit'             => 1,
                'establishments_unlimited'         => false,
                'sales_limit'                      => 25000,
                'sales_unlimited'                  => false,
                'include_sale_notes_sales_limit'   => true,
                'include_sale_notes_limit_documents'=> true,
            ],
            [
                'name'                             => 'Negocio',
                'pricing'                          => 99,
                'limit_users'                      => 10,
                'limit_documents'                  => 500,
                'plan_documents'                   => [1, 2, 3],  // + retenciones
                'locked'                           => false,
                'establishments_limit'             => 3,
                'establishments_unlimited'         => false,
                'sales_limit'                      => 0,
                'sales_unlimited'                  => true,
                'include_sale_notes_sales_limit'   => false,
                'include_sale_notes_limit_documents'=> false,
            ],
            [
                'name'                             => 'Profesional',
                'pricing'                          => 199,
                'limit_users'                      => 25,
                'limit_documents'                  => 2000,
                'plan_documents'                   => [1, 2, 3, 4], // Todo
                'locked'                           => false,
                'establishments_limit'             => 10,
                'establishments_unlimited'         => false,
                'sales_limit'                      => 0,
                'sales_unlimited'                  => true,
                'include_sale_notes_sales_limit'   => false,
                'include_sale_notes_limit_documents'=> false,
            ],
            [
                'name'                             => 'Enterprise',
                'pricing'                          => 399,
                'limit_users'                      => 0,          // Ilimitado
                'limit_documents'                  => 0,          // Ilimitado
                'plan_documents'                   => [1, 2, 3, 4],
                'locked'                           => false,
                'establishments_limit'             => 0,
                'establishments_unlimited'         => true,
                'sales_limit'                      => 0,
                'sales_unlimited'                  => true,
                'include_sale_notes_sales_limit'   => false,
                'include_sale_notes_limit_documents'=> false,
            ],
        ];

        foreach ($plans as $data) {
            Plan::updateOrCreate(
                ['name' => $data['name']],
                $data
            );
        }
    }

    protected function seedPlanFeatures(): void
    {
        // Obtener IDs de features
        $features = DB::table('features')->pluck('id', 'key')->toArray();
        if (empty($features)) {
            return; // Features no han sido migradas aún
        }

        // Obtener IDs de planes
        $plans = Plan::pluck('id', 'name')->toArray();

        // ── Mapeo: plan → features incluidas ──
        // null en limit = ilimitado, número = límite metered
        $map = [
            'Gratis' => [
                // Solo POS e inventario básico, sin ecommerce ni módulos avanzados
            ],

            'Tienda Virtual' => [
                'ecommerce'    => ['limit' => null],
                'variants'     => ['limit' => null],
                'promotions'   => ['limit' => null],
                'google_login' => ['limit' => null],
            ],

            'Emprendedor' => [
                'ecommerce'    => ['limit' => null],
                'google_login' => ['limit' => null],
            ],

            'Negocio' => [
                'ecommerce'           => ['limit' => null],
                'smart_stock'         => ['limit' => null],
                'variants'            => ['limit' => null],
                'promotions'          => ['limit' => null],
                'culqi_preauth'       => ['limit' => null],
                'google_login'        => ['limit' => null],
                'multi_establishment' => ['limit' => null],
            ],

            'Profesional' => [
                'ecommerce'           => ['limit' => null],
                'logistic_module'     => ['limit' => null],
                'smart_stock'         => ['limit' => null],
                'variants'            => ['limit' => null],
                'promotions'          => ['limit' => null],
                'flash_sales'         => ['limit' => null],
                'advanced_reports'    => ['limit' => null],
                'multi_establishment' => ['limit' => null],
                'carrier_api'         => ['limit' => null],
                'culqi_preauth'       => ['limit' => null],
                'whatsapp_api'        => ['limit' => null],
                'google_login'        => ['limit' => null],
            ],

            'Enterprise' => [
                'ecommerce'           => ['limit' => null],
                'logistic_module'     => ['limit' => null],
                'smart_stock'         => ['limit' => null],
                'variants'            => ['limit' => null],
                'promotions'          => ['limit' => null],
                'flash_sales'         => ['limit' => null],
                'advanced_reports'    => ['limit' => null],
                'multi_establishment' => ['limit' => null],
                'carrier_api'         => ['limit' => null],
                'culqi_preauth'       => ['limit' => null],
                'whatsapp_api'        => ['limit' => null],
                'google_login'        => ['limit' => null],
                'read_replica'        => ['limit' => null],
            ],

            // El plan "Ilimitado" original también recibe todas las features
            'Ilimitado' => [
                'ecommerce'           => ['limit' => null],
                'logistic_module'     => ['limit' => null],
                'smart_stock'         => ['limit' => null],
                'variants'            => ['limit' => null],
                'promotions'          => ['limit' => null],
                'flash_sales'         => ['limit' => null],
                'advanced_reports'    => ['limit' => null],
                'multi_establishment' => ['limit' => null],
                'carrier_api'         => ['limit' => null],
                'culqi_preauth'       => ['limit' => null],
                'whatsapp_api'        => ['limit' => null],
                'google_login'        => ['limit' => null],
                'read_replica'        => ['limit' => null],
            ],
        ];

        foreach ($map as $planName => $planFeatures) {
            $planId = $plans[$planName] ?? null;
            if (!$planId) continue;

            foreach ($planFeatures as $featureKey => $config) {
                $featureId = $features[$featureKey] ?? null;
                if (!$featureId) continue;

                DB::table('plan_features')->updateOrInsert(
                    ['plan_id' => $planId, 'feature_id' => $featureId],
                    [
                        'limit'      => $config['limit'],
                        'meta'       => isset($config['meta']) ? json_encode($config['meta']) : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
