<?php

namespace App\Console\Commands;

use App\Services\Marketplace\ViewEventBuffer;
use Illuminate\Console\Command;

/**
 * Pasa a los contadores las vistas y clicks que el marketplace fue apuntando.
 *
 * Corre cada minuto desde el scheduler. Si deja de correr, lo unico que pasa
 * es que `marketplace_view_events` crece y los contadores se quedan quietos —
 * nada se pierde, se aplica entero en la siguiente pasada.
 */
class FlushMarketplaceViewEvents extends Command
{
    protected $signature = 'marketplace:flush-view-events
                            {--limit= : Cuantos eventos agregar como maximo en esta pasada}';

    protected $description = 'Agrega las vistas y clicks pendientes del marketplace a sus contadores';

    public function handle(ViewEventBuffer $buffer): int
    {
        $limite = (int) ($this->option('limit') ?: ViewEventBuffer::FLUSH_LIMIT);
        if ($limite < 1) {
            $this->error('--limit tiene que ser 1 o mas.');

            return self::FAILURE;
        }

        // Callado cuando no hay nada que hacer: esto corre cada minuto y su
        // salida va a un log, asi que una linea por pasada vacia lo llenaria
        // de ruido y taparia las pasadas que si hicieron algo. En interactivo
        // (-v) si se dice, para que no parezca que el comando no responde.
        $pendientes = $buffer->pending();
        if ($pendientes === 0) {
            if ($this->getOutput()->isVerbose()) {
                $this->info('No habia nada pendiente.');
            }

            return self::SUCCESS;
        }

        $r = $buffer->flush($limite);

        $this->info(sprintf(
            '%d eventos aplicados a %d productos (%d filas de dia). Quedan %d en el buzon.',
            $r['eventos'],
            $r['productos'],
            $r['dias'],
            $buffer->pending()
        ));

        return self::SUCCESS;
    }
}
