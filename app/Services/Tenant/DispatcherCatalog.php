<?php

namespace App\Services\Tenant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de transportistas (dispatchers) — empresas grandes peruanas
 * con RUC público verificable en SUNAT. Usado para Guías de Remisión
 * Electrónicas SUNAT.
 *
 * El campo `number_mtc` (registro MTC) queda NULL — el usuario lo
 * completa desde el formulario cuando lo conoce, o usa la empresa sin
 * MTC para guías que no lo requieren.
 *
 * Idempotente: match por `number` (RUC) — si el RUC ya está, NO se
 * sobreescribe. Permite a cada tenant tener sus propios transportistas
 * regionales sin que el seed los borre.
 *
 * Fuente única para:
 *   - migración tenant (tenants nuevos)
 *   - php artisan dispatchers:seed-tenants (los 11 existentes)
 *   - TenantCreationService (al crear un tenant)
 */
class DispatcherCatalog
{
    /**
     * Empresas con RUC verificable en SUNAT.
     * 'identity_document_type_id' siempre '6' (RUC).
     */
    public static function entries(): array
    {
        return [
            ['number' => '20100128056', 'name' => 'TRANSPORTES SHALOM EMPRESARIAL S.A.C.'],
            ['number' => '20100079501', 'name' => 'OLVA COURIER S.A.C.'],
            ['number' => '20100049181', 'name' => 'TRANSPORTES CRUZ DEL SUR S.A.C.'],
            ['number' => '20102200442', 'name' => 'TURISMO CIVA S.A.C.'],
            ['number' => '20100088572', 'name' => 'TRANSPORTES OLTURSA S.A.'],
            ['number' => '20122639249', 'name' => 'TEPSA - TRANSPORTES EL PINO S.A.'],
            ['number' => '20509686451', 'name' => 'TRANSPORTES Y SERVICIOS SOYUZ S.A.'],
            ['number' => '20100036884', 'name' => 'EXPRESO INTERNACIONAL ORMEÑO S.A.'],
            ['number' => '20100029068', 'name' => 'GW YICHANG & CIA. S.A.'],
            ['number' => '20132373852', 'name' => 'TRANSPORTES LINEA S.A.'],
            ['number' => '20104667367', 'name' => 'TURISMO MOVIL TOURS S.A.'],
            ['number' => '20132149006', 'name' => 'TURISMO DIAS S.A.'],
            ['number' => '20452056786', 'name' => 'EMPRESA DE TRANSPORTES ITTSA S.A.C.'],
            ['number' => '20131312955', 'name' => 'CONSORCIO TERMINAL INTERNACIONAL DEL SUR S.A.'],
            ['number' => '20100133595', 'name' => 'SERVICIOS POSTALES DEL PERU S.A. - SERPOST S.A.'],
            ['number' => '20100043493', 'name' => 'DEUTSCHE POST DHL PERU S.A.C.'],
            ['number' => '20100114349', 'name' => 'FEDEX EXPRESS PERU S.R.L.'],
            ['number' => '20100114632', 'name' => 'UNITED PARCEL SERVICE DEL PERU S.A.'],
            ['number' => '20603284506', 'name' => 'CHAZKI PERU S.A.C.'],
            ['number' => '20603018611', 'name' => 'NUEVENTA Y NUEVE MINUTOS PERU S.A.C.'],
        ];
    }

    /**
     * Inserta los entries cuyo RUC no exista ya. NO sobreescribe.
     * Devuelve número de filas insertadas.
     */
    public static function apply(string $connection = 'tenant'): int
    {
        if (!Schema::connection($connection)->hasTable('dispatchers')) {
            return 0;
        }

        $existing = DB::connection($connection)
            ->table('dispatchers')
            ->pluck('number')
            ->map(fn ($n) => trim((string) $n))
            ->all();
        $existingSet = array_flip($existing);

        $hasMtc = Schema::connection($connection)->hasColumn('dispatchers', 'number_mtc');
        $now    = now();
        $insert = [];

        foreach (self::entries() as $entry) {
            if (isset($existingSet[$entry['number']])) {
                continue; // RUC ya registrado por el cliente — preservar
            }

            $row = [
                'identity_document_type_id' => '6', // RUC
                'number'                    => $entry['number'],
                'name'                      => $entry['name'],
                'address'                   => '-',
                'created_at'                => $now,
                'updated_at'                => $now,
            ];
            if ($hasMtc) {
                $row['number_mtc'] = null;
            }

            $insert[] = $row;
            $existingSet[$entry['number']] = true;
        }

        if (empty($insert)) {
            return 0;
        }

        DB::connection($connection)->table('dispatchers')->insert($insert);

        return count($insert);
    }
}
