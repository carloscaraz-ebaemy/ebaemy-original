<?php

namespace App\Console\Commands;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audita una cadena legacy en la BD de cada tenant + en system.
 * Uso: php artisan audit:legacy devaemy.com
 *
 * Solo lectura. Itera los tenants con Hyn\Tenancy.
 */
class AuditLegacyDependency extends Command
{
    protected $signature = 'audit:legacy {needle}';
    protected $description = 'Audita una cadena legacy en BD multi-tenant + system';

    public function handle(Environment $env)
    {
        $needle = $this->argument('needle');
        $rows = [];

        // 1. SYSTEM DB — tabla configurations central
        $this->info("Buscando en system.configurations...");
        if (Schema::hasTable('configurations')) {
            foreach (['qr_api_url', 'qr_api_token'] as $col) {
                if (!Schema::hasColumn('configurations', $col)) continue;
                $hits = DB::table('configurations')
                    ->where($col, 'LIKE', "%{$needle}%")
                    ->select('id', $col)
                    ->get();
                foreach ($hits as $hit) {
                    $rows[] = ['SYSTEM', 'configurations', $col, $hit->id, $this->mask($hit->{$col})];
                }
            }
        }

        // 2. TENANTS — iterar los 11 (o los que existan)
        foreach (Website::all() as $website) {
            $env->tenant($website);

            $checks = [
                ['configurations', 'qr_api_url'],
                ['configurations', 'qr_api_apiKey'],
                ['configuration_ecommerce', 'whatsapp_api_token'],
                ['configuration_ecommerce', 'whatsapp_phone_number_id'],
            ];

            foreach ($checks as [$table, $column]) {
                if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) continue;
                $hits = DB::table($table)
                    ->where($column, 'LIKE', "%{$needle}%")
                    ->select('id', $column)
                    ->get();
                foreach ($hits as $hit) {
                    $rows[] = [$website->uuid, $table, $column, $hit->id, $this->mask($hit->{$column})];
                }
            }
        }

        if (empty($rows)) {
            $this->warn("Sin coincidencias para '{$needle}'");
            return 0;
        }

        $this->table(['Tenant/SYSTEM', 'Tabla', 'Columna', 'ID', 'Valor'], $rows);
        $this->info(count($rows) . " coincidencias encontradas");
        return 0;
    }

    /**
     * Maskea valores que podrían contener tokens (deja primeros 8 chars + ...).
     */
    private function mask(?string $value): string
    {
        if (!$value) return '';
        if (strlen($value) <= 30) return $value;
        return substr($value, 0, 30) . '...';
    }
}
