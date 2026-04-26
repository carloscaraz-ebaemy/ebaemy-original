<?php

namespace App\Services\Tenant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo central de empresas de transporte / courier en Perú.
 *
 * Fuente única de verdad para el seed de `courier_companies` en cada tenant.
 * Usado por:
 *   - migración tenant (para tenants nuevos vía Hyn MigratesTenants)
 *   - app/Console/Commands/SeedCourierCompanies.php (para tenants existentes)
 *   - TenantCreationService::bootstrapTenantDatabase (al crear un tenant)
 *
 * El método apply() es idempotente: hace match por nombre normalizado
 * (lower + trim). Las agencias ya registradas con el mismo nombre NO se
 * duplican ni se sobrescriben — sólo se insertan las faltantes.
 */
class CourierCompanyCatalog
{
    /**
     * Catálogo curado de empresas con presencia operativa en Perú.
     * Agrupado por categoría (no se persiste — solo orden visual).
     * sort_order: las más usadas primero.
     */
    public static function entries(): array
    {
        return [
            // ── Couriers nacionales / urbanos ──────────────────
            ['name' => 'Olva Courier',         'sort_order' => 1],
            ['name' => 'Serpost',              'sort_order' => 2],
            ['name' => 'Urbano Express',       'sort_order' => 3],
            ['name' => 'Servientrega',         'sort_order' => 4],
            ['name' => 'DHL Perú',             'sort_order' => 5],
            ['name' => 'FedEx Perú',           'sort_order' => 6],
            ['name' => 'UPS Perú',             'sort_order' => 7],

            // ── Last-mile / delivery rápido ────────────────────
            ['name' => 'Chazki',               'sort_order' => 10],
            ['name' => '99Minutos',            'sort_order' => 11],
            ['name' => 'Rappi Cargo',          'sort_order' => 12],
            ['name' => 'PedidosYa Envíos',     'sort_order' => 13],

            // ── Empresas interprovinciales (encomiendas / cargo) ─
            ['name' => 'Shalom Empresarial',   'sort_order' => 20],
            ['name' => 'Marvisur',             'sort_order' => 21],
            ['name' => 'GW Yichang',           'sort_order' => 22],
            ['name' => 'Cruz del Sur Cargo',   'sort_order' => 23],
            ['name' => 'Civa Cargo',           'sort_order' => 24],
            ['name' => 'Oltursa Cargo',        'sort_order' => 25],
            ['name' => 'Tepsa Cargo',          'sort_order' => 26],
            ['name' => 'Ittsa Cargo',          'sort_order' => 27],
            ['name' => 'Móvil Tours Cargo',    'sort_order' => 28],
            ['name' => 'Soyuz Cargo',          'sort_order' => 29],
            ['name' => 'Flores Hnos.',         'sort_order' => 30],
            ['name' => 'Línea Cargo',          'sort_order' => 31],
            ['name' => 'Turismo Días',         'sort_order' => 32],
            ['name' => 'ETUCSA',               'sort_order' => 33],
            ['name' => 'Excluciva Cargo',      'sort_order' => 34],
            ['name' => 'Anita Tours Cargo',    'sort_order' => 35],
            ['name' => 'Tournet Cargo',        'sort_order' => 36],
            ['name' => 'Saby Express',         'sort_order' => 37],
            ['name' => 'Junín Express',        'sort_order' => 38],
            ['name' => 'Selva Express',        'sort_order' => 39],
            ['name' => 'Tata Cargo',           'sort_order' => 40],
            ['name' => 'Inka Express',         'sort_order' => 41],
            ['name' => 'JET Cargo',            'sort_order' => 42],

            // ── Modos propios / presenciales ───────────────────
            ['name' => 'Motorizado propio',    'sort_order' => 90],
            ['name' => 'Recojo en tienda',     'sort_order' => 91],
            ['name' => 'Otro / sin agencia',   'sort_order' => 99],
        ];
    }

    /**
     * Inserta en `courier_companies` solo las entries cuyo nombre
     * (case/space-insensitive) no exista ya. Las existentes NO se modifican.
     *
     * Devuelve número de filas insertadas.
     */
    public static function apply(string $connection = 'tenant'): int
    {
        if (!Schema::connection($connection)->hasTable('courier_companies')) {
            return 0;
        }

        $existing = DB::connection($connection)
            ->table('courier_companies')
            ->pluck('name')
            ->map(fn ($n) => self::normalize($n))
            ->all();
        $existingSet = array_flip($existing);

        $now    = now();
        $insert = [];

        foreach (self::entries() as $entry) {
            $key = self::normalize($entry['name']);
            if (isset($existingSet[$key])) {
                continue; // ya registrada — preservar lo del cliente
            }
            $insert[] = [
                'name'        => $entry['name'],
                'is_active'   => true,
                'sort_order'  => $entry['sort_order'],
                'api_driver'  => self::hasApiColumns($connection) ? 'manual' : null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
            $existingSet[$key] = true;
        }

        if (empty($insert)) {
            return 0;
        }

        // Si la columna api_driver no existe (tenant viejo sin la migración
        // 2026_03_24_000005), eliminar esa key antes del insert.
        if (!self::hasApiColumns($connection)) {
            foreach ($insert as &$row) {
                unset($row['api_driver']);
            }
        }

        DB::connection($connection)->table('courier_companies')->insert($insert);

        return count($insert);
    }

    private static function hasApiColumns(string $connection): bool
    {
        return Schema::connection($connection)->hasColumn('courier_companies', 'api_driver');
    }

    private static function normalize(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)));
    }
}
