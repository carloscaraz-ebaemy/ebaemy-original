<?php

namespace App\Services;

use App\Models\System\Client;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WarehouseEtl — extrae datos de los tenants y los carga en el warehouse analítico.
 *
 * Filosofía:
 *   - Extracción INCREMENTAL (por fecha) para evitar reprocessar datos históricos.
 *   - Datos AGREGADOS (no registros raw) para reducir volumen y latencia.
 *   - IDEMPOTENTE: re-correr el ETL de la misma fecha sobreescribe correctamente.
 *   - FAIL-SAFE: un tenant con error no bloquea el resto.
 *
 * Tablas destino (en BD 'warehouse'):
 *   dw_daily_sales    — ventas diarias por tenant y tipo de documento
 *   dw_tenant_items   — snapshot de catálogo de productos
 *   dw_tenant_metrics — métricas globales del tenant
 *   dw_etl_log        — log de cada ejecución
 */
class WarehouseEtl
{
    private const DW = 'warehouse';

    public function __construct(private readonly Environment $tenancy) {}

    // ──────────────────────────────────────────────────────────────────────────
    // API pública
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Sincroniza un tenant específico para el rango de fechas dado.
     *
     * @param  Website     $website
     * @param  string      $from       Fecha inicio ISO (Y-m-d)
     * @param  string      $to         Fecha fin ISO (Y-m-d)
     * @param  bool        $withItems  También sincronizar snapshot de items
     */
    public function syncTenant(
        Website $website,
        string  $from,
        string  $to,
        bool    $withItems = false
    ): array {
        $logId = $this->startLog($website->uuid, 'incremental');
        $stats = ['inserted' => 0, 'updated' => 0, 'errors' => 0];

        try {
            $this->tenancy->tenant($website);

            $this->syncDailySales($website->uuid, $from, $to, $stats);

            if ($withItems) {
                $this->syncItemSnapshot($website->uuid, $to, $stats);
            }

            $this->syncTenantMetrics($website, $stats);

            $this->endLog($logId, 'success', $stats);

        } catch (\Throwable $e) {
            $stats['errors']++;
            $this->endLog($logId, 'failed', $stats, $e->getMessage());
            Log::error('[WarehouseEtl] Error sync tenant.', [
                'tenant' => $website->uuid,
                'error'  => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            $this->tenancy->tenant(null);
        }

        return $stats;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Extractores por tabla
    // ──────────────────────────────────────────────────────────────────────────

    private function syncDailySales(string $uuid, string $from, string $to, array &$stats): void
    {
        // Extraer ventas diarias agregadas del tenant activo
        // SaleNotes
        $snRows = DB::connection('tenant')
            ->table('sale_notes as sn')
            ->selectRaw("
                ? as tenant_uuid,
                DATE(sn.date_of_issue) as sale_date,
                'NV' as document_type,
                COUNT(*) as `count`,
                COALESCE(SUM(CASE WHEN sn.state_type_id NOT IN ('11','13') THEN sn.total ELSE 0 END), 0) as gross_amount,
                COALESCE(SUM(CASE WHEN sn.state_type_id IN ('01','03','05','07','13') THEN sn.total ELSE 0 END), 0) as net_amount,
                COALESCE(SUM(CASE WHEN sn.state_type_id IN ('01','03','05','07','13') THEN sn.total_igv ELSE 0 END), 0) as igv_amount,
                COUNT(DISTINCT sni.id) as items_sold,
                'PEN' as currency_code
            ", [$uuid])
            ->leftJoin('sale_note_items as sni', 'sni.sale_note_id', '=', 'sn.id')
            ->whereNotNull('sn.date_of_issue')
            ->whereBetween('sn.date_of_issue', [$from, $to])
            ->groupByRaw('DATE(sn.date_of_issue)')
            ->get()
            ->toArray();

        // Documents (facturas/boletas)
        $docRows = DB::connection('tenant')
            ->table('documents as d')
            ->selectRaw("
                ? as tenant_uuid,
                DATE(d.date_of_issue) as sale_date,
                d.document_type_id as document_type,
                COUNT(*) as `count`,
                COALESCE(SUM(d.total), 0) as gross_amount,
                COALESCE(SUM(CASE WHEN d.state_type_id IN ('01','03','05','07','13') THEN d.total ELSE 0 END), 0) as net_amount,
                COALESCE(SUM(CASE WHEN d.state_type_id IN ('01','03','05','07','13') THEN d.total_igv ELSE 0 END), 0) as igv_amount,
                COUNT(DISTINCT di.id) as items_sold,
                COALESCE(d.currency_type_id, 'PEN') as currency_code
            ", [$uuid])
            ->leftJoin('document_items as di', 'di.document_id', '=', 'd.id')
            ->whereNotNull('d.date_of_issue')
            ->whereBetween('d.date_of_issue', [$from, $to])
            ->groupByRaw('DATE(d.date_of_issue), d.document_type_id, d.currency_type_id')
            ->get()
            ->toArray();

        $allRows = array_merge($snRows, $docRows);

        foreach ($allRows as $row) {
            $row = (array) $row;
            $existing = DB::connection(self::DW)->table('dw_daily_sales')
                ->where('tenant_uuid',   $row['tenant_uuid'])
                ->where('sale_date',     $row['sale_date'])
                ->where('document_type', $row['document_type'])
                ->value('id');

            if ($existing) {
                DB::connection(self::DW)->table('dw_daily_sales')
                    ->where('id', $existing)
                    ->update(array_merge($row, ['etl_synced_at' => now()]));
                $stats['updated']++;
            } else {
                DB::connection(self::DW)->table('dw_daily_sales')
                    ->insert(array_merge($row, ['etl_synced_at' => now()]));
                $stats['inserted']++;
            }
        }
    }

    private function syncItemSnapshot(string $uuid, string $snapshotDate, array &$stats): void
    {
        // Snapshot del catálogo de items del tenant
        $ago30 = now()->subDays(30)->toDateString();

        $items = DB::connection('tenant')
            ->table('items as i')
            ->selectRaw("
                ? as tenant_uuid,
                i.id as item_id,
                i.internal_id,
                SUBSTRING(i.description, 1, 250) as description,
                i.sale_unit_price,
                i.apply_store,
                COALESCE(i.has_variants, 0) as has_variants,
                COALESCE(SUM(DISTINCT iw.stock_physical), 0) as stock_physical,
                COALESCE((
                    SELECT SUM(sni.quantity)
                    FROM sale_note_items sni
                    INNER JOIN sale_notes sn ON sn.id = sni.sale_note_id
                    WHERE sni.item_id = i.id
                      AND sn.date_of_issue >= ?
                      AND sn.state_type_id IN ('01','03','05','07','13')
                ), 0) as sales_count_30d,
                ? as snapshot_date
            ", [$uuid, $ago30, $snapshotDate])
            ->leftJoin('item_warehouse as iw', 'iw.item_id', '=', 'i.id')
            ->where('i.item_type_id', '01')
            ->groupBy('i.id', 'i.internal_id', 'i.description', 'i.sale_unit_price', 'i.apply_store', 'i.has_variants')
            ->get();

        foreach ($items as $item) {
            $row = (array) $item;
            $existing = DB::connection(self::DW)->table('dw_tenant_items')
                ->where('tenant_uuid', $uuid)
                ->where('item_id', $row['item_id'])
                ->where('snapshot_date', $snapshotDate)
                ->value('id');

            if ($existing) {
                DB::connection(self::DW)->table('dw_tenant_items')
                    ->where('id', $existing)
                    ->update(array_merge($row, ['etl_synced_at' => now()]));
                $stats['updated']++;
            } else {
                DB::connection(self::DW)->table('dw_tenant_items')
                    ->insert(array_merge($row, ['etl_synced_at' => now()]));
                $stats['inserted']++;
            }
        }
    }

    private function syncTenantMetrics(Website $website, array &$stats): void
    {
        $uuid = $website->uuid;

        // Resolver hostname y plan desde sistema
        $client = \App\Models\System\Client::where('hostname_id', function ($q) use ($uuid) {
            $q->select('id')->from('hostnames')
              ->where('website_id', function ($q2) use ($uuid) {
                  $q2->select('id')->from('websites')->where('uuid', $uuid);
              })->limit(1);
        })->with('plan')->first();

        $now = now();
        $ago30  = now()->subDays(30)->toDateString();
        $ago12m = now()->subMonths(12)->toDateString();

        $sales30 = DB::connection(self::DW)->table('dw_daily_sales')
            ->where('tenant_uuid', $uuid)
            ->where('sale_date', '>=', $ago30)
            ->sum('net_amount');

        $sales12m = DB::connection(self::DW)->table('dw_daily_sales')
            ->where('tenant_uuid', $uuid)
            ->where('sale_date', '>=', $ago12m)
            ->sum('net_amount');

        $totalDocs = DB::connection('tenant')->table('documents')->count();
        $totalSns  = DB::connection('tenant')->table('sale_notes')->count();
        $totalUsers = DB::connection('tenant')->table('users')->count();
        $totalItems = DB::connection('tenant')->table('items')->where('item_type_id', '01')->count();
        $totalCustomers = DB::connection('tenant')->table('persons')->where('type', 'customers')->count();
        $activeEcom = DB::connection('tenant')->table('items')->where('apply_store', true)->count();

        $lastSale = DB::connection('tenant')->table('sale_notes')
            ->orderByDesc('date_of_issue')
            ->value('date_of_issue');

        // Detectar módulos activos inspeccionando datos del tenant
        $hasEcommerce  = $activeEcom > 0;
        $hasLogistic   = DB::connection('tenant')->table('logistic_orders')->exists();
        $hasSmartStock = DB::connection('tenant')
            ->table('item_warehouse')
            ->where(function ($q) {
                $q->where('stock_committed', '>', 0)->orWhereNotNull('stock_committed');
            })
            ->exists();

        $row = [
            'tenant_uuid'            => $uuid,
            'tenant_hostname'        => optional($client?->hostname)->fqdn ?? null,
            'plan_name'              => $client?->plan?->name ?? null,
            'total_users'            => $totalUsers,
            'total_items'            => $totalItems,
            'total_customers'        => $totalCustomers,
            'total_documents'        => $totalDocs,
            'total_sale_notes'       => $totalSns,
            'sales_last_30d'         => round((float) $sales30, 2),
            'sales_last_12m'         => round((float) $sales12m, 2),
            'active_items_ecommerce' => $activeEcom,
            'has_ecommerce'          => $hasEcommerce,
            'has_logistic'           => $hasLogistic,
            'has_smart_stock'        => $hasSmartStock,
            'last_sale_at'           => $lastSale,
            'etl_synced_at'          => $now,
        ];

        $existing = DB::connection(self::DW)->table('dw_tenant_metrics')
            ->where('tenant_uuid', $uuid)->value('id');

        if ($existing) {
            DB::connection(self::DW)->table('dw_tenant_metrics')
                ->where('id', $existing)->update($row);
            $stats['updated']++;
        } else {
            DB::connection(self::DW)->table('dw_tenant_metrics')->insert($row);
            $stats['inserted']++;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ETL Log
    // ──────────────────────────────────────────────────────────────────────────

    private function startLog(string $uuid, string $type): int
    {
        return DB::connection(self::DW)->table('dw_etl_log')->insertGetId([
            'tenant_uuid' => $uuid,
            'job_type'    => $type,
            'status'      => 'running',
            'started_at'  => now(),
        ]);
    }

    private function endLog(int $logId, string $status, array $stats, ?string $error = null): void
    {
        DB::connection(self::DW)->table('dw_etl_log')->where('id', $logId)->update([
            'status'        => $status,
            'rows_inserted' => $stats['inserted'],
            'rows_updated'  => $stats['updated'],
            'error_message' => $error,
            'finished_at'   => now(),
        ]);
    }
}
