<?php

namespace App\Console\Commands;

use App\Services\System\ShalomAgencyCatalog;
use Illuminate\Console\Command;

/**
 * Copia el catálogo de oficinas de Shalom a `shalom_agencies`.
 * Programado cada 3 h; si el API falla, la tabla queda como estaba y el
 * error se ve en /admin/shalom.
 */
class ShalomSyncAgencies extends Command
{
    protected $signature = 'shalom:sync-agencies';

    protected $description = 'Sincroniza las oficinas de Shalom (shalom-api.lat) con la base del sistema';

    public function handle(ShalomAgencyCatalog $catalog): int
    {
        try {
            $r = $catalog->sync();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Oficinas: {$r['total']} (nuevas {$r['created']}, actualizadas {$r['updated']}, ya no listadas {$r['delisted']})");

        return self::SUCCESS;
    }
}
