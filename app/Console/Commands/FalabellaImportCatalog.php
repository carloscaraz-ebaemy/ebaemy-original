<?php

namespace App\Console\Commands;

use App\Models\Tenant\MarketplaceChannel;
use App\Services\Marketplace\FalabellaImportService;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;

/**
 * Importa el catálogo de Saga Falabella hacia EBAEMY (crea items + enlaces).
 *
 * Uso:
 *   php artisan marketplace:falabella-import --tenant=UUID --dry-run     # solo reporta
 *   php artisan marketplace:falabella-import --tenant=UUID               # crea items
 *   php artisan marketplace:falabella-import --tenant=UUID --with-images # + descarga imágenes
 *   php artisan marketplace:falabella-import --tenant=UUID --limit=10    # solo 10 (pruebas)
 */
class FalabellaImportCatalog extends Command
{
    protected $signature = 'marketplace:falabella-import
                            {--tenant= : UUID del website (obligatorio)}
                            {--dry-run : No escribe nada, solo muestra qué haría}
                            {--with-images : Descarga y procesa las imágenes (lento)}
                            {--limit=1000 : Máximo de productos a traer}';

    protected $description = 'Importa el catálogo de Saga Falabella hacia EBAEMY (crea items + enlaces)';

    public function handle(): int
    {
        $uuid = $this->option('tenant');
        if (!$uuid) {
            $this->error('Falta --tenant=UUID. Es obligatorio para saber a qué tienda importar.');
            return self::FAILURE;
        }

        $website = Website::where('uuid', $uuid)->first();
        if (!$website) {
            $this->error("No existe el tenant con uuid {$uuid}.");
            return self::FAILURE;
        }

        app(Environment::class)->tenant($website);

        // La creación de items dispara hooks que leen auth()->user()->establishment
        // (auto-provisión de almacén/inventario). En CLI no hay usuario → autenticamos
        // como un admin del tenant para que el establecimiento resuelva.
        $user = \App\Models\Tenant\User::whereNotNull('establishment_id')->first()
            ?? \App\Models\Tenant\User::first();
        if (!$user) {
            $this->error("El tenant {$uuid} no tiene usuarios; no se puede crear inventario.");
            return self::FAILURE;
        }
        auth()->setUser($user);

        $channel = MarketplaceChannel::platform('falabella')->first();
        if (!$channel) {
            $this->error("El tenant {$uuid} no tiene un canal Saga Falabella configurado.");
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $withImages = (bool) $this->option('with-images');
        $limit = (int) $this->option('limit');

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Importando catálogo Saga → tenant {$uuid}" . ($withImages ? ' (con imágenes)' : ''));

        $service = new FalabellaImportService($channel, $withImages);
        $summary = $service->import($dryRun, $limit);

        $this->newLine();
        $this->table(
            ['Traídos', 'Creados', 'Precios actualizados', 'Enlazados', 'Saltados', 'Fallidos'],
            [[$summary['fetched'], $summary['created'], $summary['updated'] ?? 0, $summary['linked'], $summary['skipped'], $summary['failed']]]
        );

        // Mostrar primeras filas como muestra
        $sample = array_slice($summary['rows'], 0, 15);
        if ($sample) {
            $this->newLine();
            $this->line($dryRun ? 'Muestra de lo que se crearía/enlazaría:' : 'Muestra de resultados:');
            $rows = array_map(fn($r) => [
                substr($r['sku'] ?? '', 0, 28),
                $r['action'] ?? '',
                substr($r['name'] ?? ($r['error'] ?? ''), 0, 40),
                $r['price'] ?? '',
                $r['stock'] ?? '',
            ], $sample);
            $this->table(['SellerSku', 'Acción', 'Nombre / Error', 'Precio', 'Stock'], $rows);
        }

        if ($summary['failed'] > 0) {
            $this->warn("{$summary['failed']} productos fallaron. Revisa storage/logs (canal payments).");
        }

        $this->info('Listo.');
        return self::SUCCESS;
    }
}
