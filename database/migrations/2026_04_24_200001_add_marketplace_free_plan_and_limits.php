<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarding marketplace — Fase 1 (1.1 + 1.3 + 1.5).
 *
 * 1) Añade el feature `marketplace_products_limit` (metered).
 * 2) Crea el plan "Gratis" (S/ 0) que recibirán por defecto los sellers nuevos
 *    aprobados desde /seller/register.
 * 3) Asigna `marketplace_products_limit` con limit=25 al plan Gratis y limit=null
 *    (ilimitado) a los planes que ya incluían `ecommerce`.
 * 4) Añade `auto_approve_sellers` y `seller_default_plan_id` a `configurations`
 *    para que el SuperAdmin pueda activar la autoaprobación de sellers cuando
 *    el RUC valide ACTIVO+HABIDO contra SUNAT.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('features')->updateOrInsert(
            ['key' => 'marketplace_products_limit'],
            [
                'name'        => 'Límite de productos publicables en marketplace',
                'description' => 'Cantidad máxima de productos que un seller puede mantener publicados en el marketplace central. null = ilimitado, 0 = no incluido.',
                'category'    => 'marketplace',
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        DB::table('plans')->updateOrInsert(
            ['name' => 'Gratis'],
            [
                'pricing'                            => 0,
                'limit_users'                        => 1,
                'limit_documents'                    => 0,
                'establishments_limit'               => 1,
                'establishments_unlimited'           => false,
                'sales_limit'                        => 0,
                'sales_unlimited'                    => true,
                'plan_documents'                     => '[]',
                'locked'                             => false,
                'is_default'                         => false,
                'include_sale_notes_sales_limit'     => false,
                'include_sale_notes_limit_documents' => false,
                'created_at'                         => now(),
                'updated_at'                         => now(),
            ]
        );

        $featureIds = DB::table('features')->pluck('id', 'key')->toArray();
        $planIds    = DB::table('plans')->pluck('id', 'name')->toArray();

        $marketplaceFeatureId = $featureIds['marketplace_products_limit'] ?? null;
        $ecommerceFeatureId   = $featureIds['ecommerce'] ?? null;

        if ($marketplaceFeatureId !== null) {
            // Plan Gratis: ecommerce + marketplace con límite 25
            if (isset($planIds['Gratis'])) {
                $planId = $planIds['Gratis'];

                if ($ecommerceFeatureId !== null) {
                    DB::table('plan_features')->updateOrInsert(
                        ['plan_id' => $planId, 'feature_id' => $ecommerceFeatureId],
                        ['limit' => null, 'meta' => null, 'created_at' => now(), 'updated_at' => now()]
                    );
                }

                DB::table('plan_features')->updateOrInsert(
                    ['plan_id' => $planId, 'feature_id' => $marketplaceFeatureId],
                    ['limit' => 25, 'meta' => null, 'created_at' => now(), 'updated_at' => now()]
                );
            }

            // Resto de planes con ecommerce → marketplace ilimitado
            $unlimitedPlans = ['Tienda Web', 'Negocio', 'Pro', 'Enterprise'];
            foreach ($unlimitedPlans as $planName) {
                $planId = $planIds[$planName] ?? null;
                if ($planId === null) {
                    continue;
                }

                DB::table('plan_features')->updateOrInsert(
                    ['plan_id' => $planId, 'feature_id' => $marketplaceFeatureId],
                    ['limit' => null, 'meta' => null, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        Schema::table('configurations', function (Blueprint $table) {
            if (!Schema::hasColumn('configurations', 'auto_approve_sellers')) {
                $table->boolean('auto_approve_sellers')->default(false)->after('send_notification_cron');
            }
            if (!Schema::hasColumn('configurations', 'seller_default_plan_id')) {
                $table->unsignedInteger('seller_default_plan_id')->nullable()->after('auto_approve_sellers');
            }
            if (!Schema::hasColumn('configurations', 'seller_requires_active_ruc')) {
                $table->boolean('seller_requires_active_ruc')->default(true)->after('seller_default_plan_id');
            }
        });

        // Si el SuperAdmin no eligió plan default, sembrar Gratis automáticamente
        // para que la autoaprobación tenga un destino válido cuando se active.
        $gratisId = $planIds['Gratis'] ?? DB::table('plans')->where('name', 'Gratis')->value('id');
        if ($gratisId) {
            DB::table('configurations')
                ->whereNull('seller_default_plan_id')
                ->update(['seller_default_plan_id' => $gratisId]);
        }
    }

    public function down(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            foreach (['seller_requires_active_ruc', 'seller_default_plan_id', 'auto_approve_sellers'] as $col) {
                if (Schema::hasColumn('configurations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        $featureId = DB::table('features')->where('key', 'marketplace_products_limit')->value('id');
        if ($featureId) {
            DB::table('plan_features')->where('feature_id', $featureId)->delete();
            DB::table('features')->where('id', $featureId)->delete();
        }

        DB::table('plan_features')
            ->whereIn('plan_id', DB::table('plans')->where('name', 'Gratis')->pluck('id'))
            ->delete();
        DB::table('plans')->where('name', 'Gratis')->delete();
    }
};
