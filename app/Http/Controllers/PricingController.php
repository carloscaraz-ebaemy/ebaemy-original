<?php

namespace App\Http\Controllers;

use App\Models\System\Feature;
use App\Models\System\Plan;

/**
 * Página pública de comparativa de planes (/precios).
 *
 * Lista los planes activos en system.plans con sus features asignados,
 * ordenados por precio. CTA "Crear mi tienda gratis" lleva a /seller/register.
 *
 * Reutiliza el layout del marketplace para coherencia visual y para que
 * los visitantes vean el contexto (header con buscador, etc.).
 */
class PricingController extends Controller
{
    public function show()
    {
        // Etiquetas legibles por feature key. Centralizado aquí para no
        // depender de cómo el seeder llamó al campo `name` (que puede ser
        // técnico). Si el feature no está mapeado, usamos su `name`.
        $labels = [
            'ecommerce'                  => '🏬 Tienda virtual con subdominio',
            'marketplace_products_limit' => '🛒 Publicar productos en marketplace',
            'promotions'                 => '🎟️ Cupones y promociones',
            'variants'                   => '🎨 Variantes (talla, color, etc.)',
            'flash_sales'                => '⚡ Flash sales',
            'logistic_module'            => '📦 Módulo logístico (almacén + despachos)',
            'smart_stock'                => '🧠 Smart Stock (físico/comprometido/disponible)',
            'multi_establishment'        => '🏢 Múltiples establecimientos',
            'advanced_reports'           => '📊 Reportes avanzados',
            'google_login'               => '🔐 Login con Google',
            'culqi_preauth'              => '💳 Pago Culqi pre-autorización',
            'whatsapp_api'               => '💬 WhatsApp API (Meta Cloud)',
            'carrier_api'                => '🚚 Integración carrier API',
            'read_replica'               => '⚙️ Réplica de lectura (escalado)',
        ];

        // Features de cuota: limit=0 significa "no incluida" (no tiene sentido
        // 'hasta 0'). Para los demás (booleanos), limit=0 ó null = "incluida".
        $quotaFeatures = ['marketplace_products_limit'];

        $plans = Plan::query()
            ->with(['features' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('pricing')
            ->get()
            ->map(function (Plan $plan) use ($labels, $quotaFeatures) {
                $features = $plan->features
                    ->filter(function (Feature $f) use ($quotaFeatures) {
                        // Solo descartamos features de cuota con limit=0.
                        return !(in_array($f->key, $quotaFeatures, true) && $f->pivot->limit === 0);
                    })
                    ->map(function (Feature $f) use ($labels, $quotaFeatures) {
                        $limit  = $f->pivot->limit;
                        $label  = $labels[$f->key] ?? $f->name;
                        $isQuota = in_array($f->key, $quotaFeatures, true);

                        // Sufijo 'hasta N' solo aplica a cuotas con valor > 0.
                        if ($isQuota && $limit !== null && $limit > 0) {
                            $label .= " (hasta {$limit})";
                        }
                        return [
                            'key'   => $f->key,
                            'label' => $label,
                            // Normalizamos: si no es cuota, tratamos limit como null
                            // (features booleanos no necesitan número).
                            'limit' => $isQuota ? $limit : null,
                        ];
                    })->values();

                return (object) [
                    'id'            => $plan->id,
                    'name'          => $plan->name,
                    'price'         => (float) $plan->pricing,
                    'is_free'       => (float) $plan->pricing == 0,
                    'is_default'    => (bool) $plan->is_default,
                    'limit_users'   => (int) $plan->limit_users,
                    'establishments' => $plan->establishments_unlimited
                        ? 'Ilimitados'
                        : (string) $plan->establishments_limit,
                    'features'      => $features,
                ];
            });

        return view('marketplace.pricing', compact('plans'));
    }
}
