<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reseed canónico de plan_features.
 *
 * Corrige inconsistencias que rompían la lógica SaaS de superset
 * (cada plan superior debe incluir todo lo del anterior):
 *   - Flash Sales aparecía solo en Pro/Enterprise; ahora desde Negocio.
 *   - Google Login solo en Pro/Enterprise; ahora desde Tienda Web.
 *   - Culqi Pre-auth solo en Pro/Enterprise; ahora desde Negocio.
 *   - Multi-establishment como boolean; ahora con cuotas (Negocio=2, Pro=3, Enterprise=∞).
 *
 * Añade dos features nuevos para que la tabla comparativa muestre
 * explícitamente "Facturación SUNAT" y "POS punto de venta":
 *   - electronic_invoicing
 *   - pos
 *
 * Estrategia: para cada plan, BORRA sus plan_features y los REINSERTA
 * según la matriz canónica. Idempotente: correr múltiples veces da el
 * mismo resultado. NO afecta tenants — solo el catálogo system.
 *
 * Caserito sigue siendo plan paralelo (POS local sin ecommerce/marketplace).
 */
return new class extends Migration {
    public function up(): void
    {
        $now = now();

        // ── 1) Features faltantes ──────────────────────────────────────────
        $newFeatures = [
            [
                'key'         => 'electronic_invoicing',
                'name'        => 'Facturación electrónica SUNAT',
                'description' => 'Emisión de boletas, facturas y notas de crédito/débito con OSE/PSE.',
                'category'    => 'module',
                'is_active'   => true,
            ],
            [
                'key'         => 'pos',
                'name'        => 'POS punto de venta',
                'description' => 'Punto de venta para tienda física con caja y arqueo.',
                'category'    => 'module',
                'is_active'   => true,
            ],
        ];

        foreach ($newFeatures as $f) {
            DB::table('features')->updateOrInsert(
                ['key' => $f['key']],
                array_merge($f, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        // ── 2) Cargar IDs ──────────────────────────────────────────────────
        $featureIds = DB::table('features')->pluck('id', 'key')->toArray();
        $planIds    = DB::table('plans')->pluck('id', 'name')->toArray();

        // ── 3) Matriz canónica plan → [feature_key => limit] ───────────────
        // Convención del valor:
        //   null    → incluido sin cuota (✓)
        //   int > 0 → incluido con cuota (hasta N)
        //   ausente → NO incluido (—)
        $matrix = [
            'Gratis' => [
                'ecommerce'                  => null,
                'marketplace_products_limit' => 25,
            ],
            'Tienda Web' => [
                'ecommerce'                  => null,
                'marketplace_products_limit' => null,
                'google_login'               => null,
            ],
            'Caserito' => [
                'electronic_invoicing'       => null,
                'pos'                        => null,
                'google_login'               => null,
            ],
            'Negocio' => [
                'ecommerce'                  => null,
                'marketplace_products_limit' => null,
                'electronic_invoicing'       => null,
                'pos'                        => null,
                'variants'                   => null,
                'promotions'                 => null,
                'flash_sales'                => null,
                'google_login'               => null,
                'culqi_preauth'              => null,
                'multi_establishment'        => 2,
            ],
            'Pro' => [
                'ecommerce'                  => null,
                'marketplace_products_limit' => null,
                'electronic_invoicing'       => null,
                'pos'                        => null,
                'variants'                   => null,
                'promotions'                 => null,
                'flash_sales'                => null,
                'google_login'               => null,
                'culqi_preauth'              => null,
                'multi_establishment'        => 3,
                'smart_stock'                => null,
                'logistic_module'            => null,
                'advanced_reports'           => null,
            ],
            'Enterprise' => [
                'ecommerce'                  => null,
                'marketplace_products_limit' => null,
                'electronic_invoicing'       => null,
                'pos'                        => null,
                'variants'                   => null,
                'promotions'                 => null,
                'flash_sales'                => null,
                'google_login'               => null,
                'culqi_preauth'              => null,
                'multi_establishment'        => null, // ilimitado
                'smart_stock'                => null,
                'logistic_module'            => null,
                'advanced_reports'           => null,
                'carrier_api'                => null,
                'whatsapp_api'               => null,
                'read_replica'               => null,
            ],
        ];

        // ── 4) Aplicar matriz: por cada plan, borra y reinserta ────────────
        foreach ($matrix as $planName => $features) {
            $planId = $planIds[$planName] ?? null;
            if (!$planId) {
                continue; // plan no creado en este sistema, saltar sin error
            }

            DB::table('plan_features')->where('plan_id', $planId)->delete();

            $rows = [];
            foreach ($features as $featureKey => $limit) {
                $featureId = $featureIds[$featureKey] ?? null;
                if (!$featureId) {
                    continue; // feature no existe (raro), saltar
                }
                $rows[] = [
                    'plan_id'    => $planId,
                    'feature_id' => $featureId,
                    'limit'      => $limit,
                    'meta'       => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($rows)) {
                DB::table('plan_features')->insert($rows);
            }
        }
    }

    public function down(): void
    {
        // No-op: revertir a la matriz anterior implicaría re-aplicar el seed
        // antiguo que tenía las inconsistencias; preferimos no automatizar
        // ese rollback. Si necesitas revertir, edita manualmente o restaura
        // backup de plan_features.
    }
};
