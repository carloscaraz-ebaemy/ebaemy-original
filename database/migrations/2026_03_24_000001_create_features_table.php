<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de feature flags del sistema.
 *
 * Cada feature representa una capacidad opcional que puede estar o no
 * incluida en un plan. Reemplaza el enfoque de booleans hard-coded en
 * la tabla `plans` (limit_users, include_sale_notes_limit_documents, etc.)
 * para features nuevas.
 *
 * Los features existentes en Plan siguen vigentes para compatibilidad.
 * Nuevos features se añaden aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('features')) {
            Schema::create('features', function (Blueprint $table) {
                $table->increments('id');
                $table->string('key', 60)->unique();       // identificador de código: 'ecommerce', 'smart_stock'
                $table->string('name', 120);               // nombre legible
                $table->string('description', 500)->nullable();
                $table->string('category', 50)->default('module'); // module | integration | limit
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // ── Catálogo inicial de features ──────────────────────────────────────
        $features = [
            ['key' => 'ecommerce',          'name' => 'Módulo Ecommerce',           'category' => 'module',      'description' => 'Tienda en línea, carrito, checkout, Culqi.'],
            ['key' => 'logistic_module',    'name' => 'Módulo Logístico',           'category' => 'module',      'description' => 'Cola de almacén, despacho, guías de remisión.'],
            ['key' => 'smart_stock',        'name' => 'Smart Stock',                'category' => 'module',      'description' => 'Stock triple (físico, comprometido, disponible) + reservas.'],
            ['key' => 'variants',           'name' => 'Variantes de Productos',     'category' => 'module',      'description' => 'Opciones de producto (talla, color) con stock independiente.'],
            ['key' => 'promotions',         'name' => 'Promociones y Cupones',      'category' => 'module',      'description' => 'Motor de descuentos, cupones, puntos de fidelidad.'],
            ['key' => 'flash_sales',        'name' => 'Flash Sales',                'category' => 'module',      'description' => 'Ventas con tiempo limitado y precio especial.'],
            ['key' => 'carrier_api',        'name' => 'Integración Carrier API',    'category' => 'integration', 'description' => 'Conexión automática con Chazki, 99Minutos y otros.'],
            ['key' => 'culqi_preauth',      'name' => 'Culqi Pre-autorización',     'category' => 'integration', 'description' => 'Cobro asíncrono con pre-auth + captura diferida.'],
            ['key' => 'whatsapp_api',       'name' => 'WhatsApp Business API',      'category' => 'integration', 'description' => 'Notificaciones por WhatsApp vía API oficial.'],
            ['key' => 'google_login',       'name' => 'Login con Google',           'category' => 'integration', 'description' => 'Autenticación ecommerce con cuenta Google.'],
            ['key' => 'advanced_reports',   'name' => 'Reportes Avanzados',         'category' => 'module',      'description' => 'Dashboard V2 con KPIs, gráficas y comparativas.'],
            ['key' => 'multi_establishment','name' => 'Multi-establecimiento',      'category' => 'module',      'description' => 'Más de un establecimiento/local.'],
            ['key' => 'read_replica',       'name' => 'Read Replica (reporting)',   'category' => 'integration', 'description' => 'Consultas de reportes en réplica MySQL de solo-lectura.'],
        ];

        foreach ($features as $feature) {
            DB::table('features')->updateOrInsert(
                ['key' => $feature['key']],
                $feature
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
