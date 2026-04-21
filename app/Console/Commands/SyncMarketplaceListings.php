<?php

namespace App\Console\Commands;

use App\Models\System\Client;
use App\Models\System\MarketplaceListing;
use Hyn\Tenancy\Environment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sincroniza los items publicables de cada tenant al índice central
 * marketplace_listings. Usa pull directo a la BD del tenant para evitar
 * dependencias de red — el central tiene acceso a todas las BDs tenant.
 *
 * Corre manualmente: php artisan marketplace:sync
 * Puede programarse en Kernel::schedule para pulls periódicos.
 */
class SyncMarketplaceListings extends Command
{
    protected $signature = 'ebaemy-marketplace:sync {--client= : Sincronizar solo un client_id} {--dry-run : No escribir cambios}';
    protected $description = 'Sincroniza items publicables de cada tenant al marketplace central (ebaemy.com/marketplace)';

    public function handle(): int
    {
        $clients = Client::query()
            ->when($this->option('client'), fn($q, $id) => $q->where('id', $id))
            ->whereHas('hostname.website')
            ->with('hostname.website')
            ->get();

        if ($clients->isEmpty()) {
            $this->warn('No hay tenants para sincronizar.');
            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $tenancy = app(Environment::class);

        $totalSynced = 0;
        $totalRemoved = 0;

        foreach ($clients as $client) {
            $hostname = $client->hostname;
            if (!$hostname || !$hostname->website) {
                $this->warn("Client {$client->id} sin hostname/website — omitido");
                continue;
            }

            $this->info("→ Sync tenant {$hostname->fqdn} (client_id={$client->id})");

            try {
                $tenancy->tenant($hostname->website);

                $items = DB::connection('tenant')->table('items')
                    ->where('marketplace_publishable', true)
                    ->where(function ($q) {
                        $q->whereNull('mp_status')->orWhere('mp_status', '!=', 'rejected');
                    })
                    ->where('active', true)
                    ->get();

                $seenRemoteIds = [];

                foreach ($items as $it) {
                    $seenRemoteIds[] = (int) $it->id;

                    $stockPhysical = DB::connection('tenant')->table('item_warehouse')
                        ->where('item_id', $it->id)
                        ->sum('stock');

                    $imageUrl = $it->image
                        ? rtrim(($hostname->fqdn ? 'https://' . $hostname->fqdn : url('/')), '/')
                          . '/storage/uploads/items/' . $it->image
                        : null;

                    $categoryName = null;
                    if (!empty($it->category_id)) {
                        $categoryName = DB::connection('tenant')->table('categories')
                            ->where('id', $it->category_id)
                            ->value('name');
                    }

                    $brandName = null;
                    if (!empty($it->brand_id)) {
                        $brandName = DB::connection('tenant')->table('brands')
                            ->where('id', $it->brand_id)
                            ->value('name');
                    }

                    $payload = [
                        'hostname_id'       => $hostname->id,
                        'tenant_fqdn'       => $hostname->fqdn,
                        'client_id'         => $client->id,
                        'remote_item_id'    => $it->id,
                        'title'             => Str::limit((string) ($it->description ?: $it->name ?: 'Producto'), 250, ''),
                        'slug'              => $this->buildSlug($it, $hostname->id),
                        'internal_id'       => $it->internal_id,
                        'short_description' => null,
                        'description'       => $it->mp_notes ?: null,
                        'image_url'         => $imageUrl,
                        'category_name'     => $categoryName,
                        'brand_name'        => $brandName,
                        'price'             => (float) ($it->sale_unit_price ?? 0),
                        'mp_price'          => $it->mp_price !== null ? (float) $it->mp_price : null,
                        'stock'             => max(0, (int) $stockPhysical),
                        'status'            => $it->mp_status ?: 'active',
                        'is_active'         => true,
                        'synced_at'         => now(),
                    ];

                    if ($dry) {
                        $this->line("  · [dry] {$payload['title']}  S/ {$payload['price']}  stock={$payload['stock']}");
                        continue;
                    }

                    // Cambiar de conexión al central explícitamente para el upsert
                    $tenancy->tenant(null);

                    MarketplaceListing::updateOrCreate(
                        ['hostname_id' => $hostname->id, 'remote_item_id' => $it->id],
                        $payload
                    );

                    // Volver al tenant para la siguiente iteración
                    $tenancy->tenant($hostname->website);

                    $totalSynced++;
                }

                // Desactivar listings de items que ya no están publicables
                $tenancy->tenant(null);
                $removed = MarketplaceListing::where('hostname_id', $hostname->id)
                    ->when(!empty($seenRemoteIds), fn($q) => $q->whereNotIn('remote_item_id', $seenRemoteIds))
                    ->where('is_active', true)
                    ->update(['is_active' => false, 'status' => 'paused', 'synced_at' => now()]);

                $totalRemoved += $removed;

                $this->line("  ✓ {$items->count()} items sincronizados, {$removed} desactivados");
            } catch (\Throwable $e) {
                Log::error('marketplace:sync error', [
                    'client_id' => $client->id,
                    'hostname'  => $hostname->fqdn,
                    'error'     => $e->getMessage(),
                ]);
                $this->error("  ✗ Error: {$e->getMessage()}");
            } finally {
                $tenancy->tenant(null);
            }
        }

        $this->newLine();
        $this->info("Total sincronizados: {$totalSynced}");
        $this->info("Total desactivados:  {$totalRemoved}");

        return self::SUCCESS;
    }

    /**
     * Slug único globalmente — combina slug del item con identificador del tenant
     * para evitar colisiones entre productos homónimos de distintas tiendas.
     */
    private function buildSlug($item, int $hostnameId): string
    {
        $base = $item->slug ?: Str::slug($item->description ?: $item->name ?: 'producto');
        return Str::limit($base, 180, '') . '-t' . $hostnameId . '-' . $item->id;
    }
}
