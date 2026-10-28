<?php

namespace App\Services\System;

use App\Models\System\Configuration;
use App\Models\System\ShalomAgency;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Catálogo de oficinas de Shalom guardado en nuestra base.
 *
 * `sync()` lo trae del API (cada 3 h por el scheduler, que es lo que tarda
 * el origen en refrescarse, o a mano desde /admin/shalom). Todo lo demás lee
 * de `shalom_agencies`: si la llave se vence, el formulario sigue con la
 * última lista buena.
 */
class ShalomAgencyCatalog
{
    /**
     * Por debajo de esto la respuesta se trata como rota y no se toca nada:
     * una lista vacía o a medias marcaría como cerradas oficinas que existen.
     * Hoy son ~560.
     */
    private const MIN_EXPECTED = 100;

    private const CACHE_VERSION = 'shalom_catalog_v';

    public function __construct(private ShalomApi $api) {}

    /**
     * @return array{total:int, created:int, updated:int, delisted:int}
     */
    public function sync(): array
    {
        $config = Configuration::first();

        try {
            $rows = $this->api->agencies();
            if (count($rows) < self::MIN_EXPECTED) {
                throw new RuntimeException('El API devolvió solo ' . count($rows) . ' agencias; no se tocó el catálogo.');
            }
        } catch (\Throwable $e) {
            if ($config) {
                $config->forceFill([
                    'shalom_last_error'    => mb_substr($e->getMessage(), 0, 500),
                    'shalom_last_error_at' => now(),
                ])->save();
            }
            throw $e;
        }

        $now = now();
        $seen = [];
        $created = $updated = 0;

        DB::connection('system')->transaction(function () use ($rows, $now, &$seen, &$created, &$updated) {
            foreach ($rows as $r) {
                $id = (int) ($r['ter_id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $seen[] = $id;

                $agency = ShalomAgency::find($id) ?? new ShalomAgency(['ter_id' => $id]);
                $agency->exists ? $updated++ : $created++;

                $agency->fill([
                    'name'          => $this->clean($r['lugar_over'] ?? null) ?: $this->clean($r['zona'] ?? null) ?: ('Agencia ' . $id),
                    'full_name'     => $this->clean($r['nombre'] ?? null),
                    'department'    => $this->clean($r['departamento'] ?? null),
                    'province'      => $this->clean($r['provincia'] ?? null),
                    'zone'          => $this->clean($r['zona'] ?? null),
                    // El API lo da como número: 60701 es el ubigeo 060701.
                    'ubigeo'        => !empty($r['ubi_id']) ? str_pad((string) (int) $r['ubi_id'], 6, '0', STR_PAD_LEFT) : null,
                    'address'       => mb_substr((string) $this->clean($r['direccion'] ?? null), 0, 500) ?: null,
                    'phone'         => mb_substr((string) $this->clean($r['telefono'] ?? null), 0, 60) ?: null,
                    'hours'         => mb_substr((string) $this->clean($r['hora_atencion'] ?? null), 0, 255) ?: null,
                    'details'       => $this->clean($r['detalles'] ?? null),
                    'latitude'      => is_numeric($r['latitud'] ?? null) ? (float) $r['latitud'] : null,
                    'longitude'     => is_numeric($r['longitud'] ?? null) ? (float) $r['longitud'] : null,
                    // `destino` y `ter_habilitado_OS` en 0: la oficina existe
                    // pero no recibe envíos de terceros (~60 hoy).
                    'receives'      => (int) ($r['destino'] ?? 1) === 1 && (int) ($r['ter_habilitado_OS'] ?? 1) === 1,
                    'home_delivery' => (string) ($r['ter_reparto_habilitado'] ?? '0') === '1',
                    'listed'        => true,
                    'missing_since' => null,
                    'raw'           => $r,
                ]);
                $agency->save();
            }
        });

        // Las que ya no salen se conservan: hay envíos que las apuntan.
        $delisted = ShalomAgency::whereNotIn('ter_id', $seen)->where('listed', true)
            ->update(['listed' => false, 'missing_since' => $now]);

        if ($config) {
            $config->forceFill([
                'shalom_synced_at'     => $now,
                'shalom_last_error'    => null,
                'shalom_last_error_at' => null,
            ])->save();
        }

        Cache::forever(self::CACHE_VERSION, $now->timestamp);

        return ['total' => count($seen), 'created' => $created, 'updated' => $updated, 'delisted' => $delisted];
    }

    /**
     * Oficinas para un distrito. Si el distrito no tiene ninguna (solo 278
     * distritos tienen), las de su provincia.
     *
     * @return array{scope:string, offices:array}
     *   scope: 'district' | 'province' | 'none'
     */
    public function forDistrict(string $ubigeo): array
    {
        $ubigeo = preg_replace('/\D/', '', $ubigeo);
        if (strlen($ubigeo) !== 6) {
            return ['scope' => 'none', 'offices' => []];
        }

        $key = 'shalom_offices:' . Cache::get(self::CACHE_VERSION, 0) . ':' . $ubigeo;

        return Cache::remember($key, now()->addHours(3), function () use ($ubigeo) {
            $offices = ShalomAgency::selectable()->where('ubigeo', $ubigeo)->orderBy('name')->get();
            $scope = 'district';

            if ($offices->isEmpty()) {
                $offices = ShalomAgency::selectable()->where('ubigeo', 'like', substr($ubigeo, 0, 4) . '%')
                    ->orderBy('name')->get();
                $scope = $offices->isEmpty() ? 'none' : 'province';
            }

            return ['scope' => $scope, 'offices' => $offices->map(fn (ShalomAgency $a) => $this->present($a))->all()];
        });
    }

    /** Lo que ve el cliente de una oficina. Sin `raw`. */
    public function present(ShalomAgency $a): array
    {
        return [
            'id'            => $a->ter_id,
            'name'          => $a->name,
            'address'       => $a->address,
            'hours'         => $a->hours,
            'district'      => $a->zone,
            'lat'           => $a->latitude !== null ? (float) $a->latitude : null,
            'lng'           => $a->longitude !== null ? (float) $a->longitude : null,
            'home_delivery' => $a->home_delivery,
        ];
    }

    private function clean($v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim(preg_replace('/\s+/u', ' ', (string) $v));

        return $v === '' ? null : $v;
    }
}
