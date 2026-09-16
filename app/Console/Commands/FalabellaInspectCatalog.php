<?php

namespace App\Console\Commands;

use App\Models\Tenant\MarketplaceChannel;
use App\Services\Marketplace\FalabellaService;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;

/**
 * Radiografía SOLO-LECTURA del catálogo que devuelve Saga Falabella.
 *
 * Por qué existe: importar variantes exige saber qué campo agrupa las
 * variaciones de un mismo producto, y eso no se puede deducir del código —
 * hay que mirar la respuesta real del seller. Este comando la mide y la
 * resume; NO escribe absolutamente nada en la base de datos.
 *
 *   php artisan marketplace:falabella-inspect --tenant=UUID
 *   php artisan marketplace:falabella-inspect --tenant=UUID --limit=200
 *   php artisan marketplace:falabella-inspect --tenant=UUID --dump=storage/app/saga.json
 */
class FalabellaInspectCatalog extends Command
{
    protected $signature = 'marketplace:falabella-inspect
                            {--tenant= : UUID del website (obligatorio)}
                            {--limit=100 : Cuántos productos traer para la muestra}
                            {--offset=0 : Desde qué posición}
                            {--dump= : Ruta donde guardar 2 productos completos en JSON}';

    protected $description = 'Radiografía solo-lectura del catálogo de Saga (no escribe nada)';

    public function handle(): int
    {
        $uuid = $this->option('tenant');
        if (!$uuid) {
            $this->error('Falta --tenant=UUID.');
            return self::FAILURE;
        }

        $website = Website::where('uuid', $uuid)->first();
        if (!$website) {
            $this->error("No existe el tenant con uuid {$uuid}.");
            return self::FAILURE;
        }
        app(Environment::class)->tenant($website);

        $channel = MarketplaceChannel::platform('falabella')->first();
        if (!$channel) {
            $this->error("El tenant {$uuid} no tiene canal Saga Falabella configurado.");
            return self::FAILURE;
        }

        $limit  = max(1, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));

        $params = ['Limit' => $limit];
        if ($offset > 0) {
            $params['Offset'] = $offset;
        }

        $this->info("Consultando Saga (solo lectura) — tenant {$uuid}, {$limit} productos desde {$offset}…");
        $products = (new FalabellaService($channel))->getProducts($params);

        $total = count($products);
        $this->line("Productos recibidos: {$total}");
        if ($total === 0) {
            $this->warn('Saga no devolvió productos en este rango.');
            return self::SUCCESS;
        }

        $this->campos($products, $total);
        $this->agrupacion($products, $total);
        $this->unidadesDeNegocio($products);
        $this->estados($products);
        $this->imagenesYPrecios($products);
        $this->dump($products);

        $this->newLine();
        $this->info('Listo. No se escribió nada.');

        return self::SUCCESS;
    }

    /** Qué campos existen de verdad y en cuántos productos vienen con valor. */
    private function campos(array $products, int $total): void
    {
        $count = [];
        foreach ($products as $p) {
            foreach (array_keys($p) as $k) {
                $v = $p[$k];
                $filled = is_array($v) ? !empty($v) : (trim((string) $v) !== '');
                $count[$k] = ($count[$k] ?? 0) + ($filled ? 1 : 0);
            }
        }
        ksort($count);

        $this->newLine();
        $this->line('-- CAMPOS PRESENTES (con valor / total) --');
        $this->table(
            ['Campo', 'Con valor', '%'],
            array_map(fn($k, $c) => [$k, "{$c}/{$total}", round($c * 100 / $total) . '%'], array_keys($count), $count)
        );
    }

    /** ¿Algún campo agrupa varias SellerSku? Esa es LA pregunta de las variantes. */
    private function agrupacion(array $products, int $total): void
    {
        $this->newLine();
        $this->line('-- ¿QUÉ CAMPO AGRUPA VARIANTES? --');

        $skus = array_filter(array_map(fn($p) => trim((string) data_get($p, 'SellerSku', '')), $products));
        $this->line('SellerSku distintos: ' . count(array_unique($skus)) . " de {$total}");

        // Cualquier campo escalar es candidato a "padre": si se repite entre
        // productos con SellerSku distinto, agrupa.
        $rows = [];
        foreach (['ProductId', 'ShopSku', 'Name', 'ParentSku', 'PrimaryCategory'] as $campo) {
            $vals = array_filter(array_map(fn($p) => trim((string) data_get($p, $campo, '')), $products));
            if (empty($vals)) {
                $rows[] = [$campo, 'no viene', '-', '-'];
                continue;
            }
            $grupos = array_count_values($vals);
            $conVarios = array_filter($grupos, fn($n) => $n > 1);
            $rows[] = [
                $campo,
                count($vals) . ' con valor',
                count($grupos) . ' distintos',
                count($conVarios) . ' repetidos (max ' . max($grupos) . ' skus)',
            ];
        }
        $this->table(['Campo', 'Presencia', 'Valores', '¿Agrupa?'], $rows);

        $variation = array_filter(array_map(fn($p) => trim((string) data_get($p, 'Variation', '')), $products));
        $this->line('Variation con valor: ' . count($variation) . "/{$total}");
        if ($variation) {
            $muestra = array_slice(array_unique($variation), 0, 12);
            $this->line('  valores de ejemplo: ' . implode(' / ', $muestra));
        }

        // Ejemplo concreto del grupo más grande por ProductId (si agrupa).
        $porProducto = [];
        foreach ($products as $p) {
            $pid = trim((string) data_get($p, 'ProductId', ''));
            if ($pid !== '') {
                $porProducto[$pid][] = trim((string) data_get($p, 'SellerSku', '')) . ' [' . trim((string) data_get($p, 'Variation', '')) . ']';
            }
        }
        $porProducto = array_filter($porProducto, fn($g) => count($g) > 1);
        if ($porProducto) {
            $this->newLine();
            $this->line('Ejemplos de ProductId con VARIAS SellerSku (esto seria un producto con variantes):');
            foreach (array_slice($porProducto, 0, 3, true) as $pid => $g) {
                $this->line("  ProductId {$pid} -> " . implode(', ', array_slice($g, 0, 6)));
            }
        } else {
            $this->line('Ningun ProductId se repite: en esta muestra NO hay productos con variantes agrupados por ProductId.');
        }
    }

    private function unidadesDeNegocio(array $products): void
    {
        $this->newLine();
        $this->line('-- UNIDADES DE NEGOCIO --');
        $dist = [];
        $ops = [];
        foreach ($products as $p) {
            $bu = data_get($p, 'BusinessUnits.BusinessUnit');
            $n = isset($bu[0]) ? count($bu) : (empty($bu) ? 0 : 1);
            $dist[$n] = ($dist[$n] ?? 0) + 1;
            foreach ((isset($bu[0]) ? $bu : [$bu]) as $u) {
                $code = trim((string) (data_get($u, 'OperatorCode') ?: data_get($u, 'Name') ?: ''));
                if ($code !== '') {
                    $ops[$code] = ($ops[$code] ?? 0) + 1;
                }
            }
        }
        ksort($dist);
        foreach ($dist as $n => $c) {
            $this->line("  productos con {$n} unidad(es) de negocio: {$c}");
        }
        arsort($ops);
        $this->line('  OperatorCode vistos: ' . (empty($ops) ? '(ninguno)' : implode(', ', array_map(fn($k, $v) => "{$k} (x{$v})", array_keys($ops), $ops))));
    }

    private function estados(array $products): void
    {
        $this->newLine();
        $this->line('-- ESTADOS --');
        $st = [];
        foreach ($products as $p) {
            $bu = data_get($p, 'BusinessUnits.BusinessUnit');
            if (isset($bu[0])) {
                $bu = $bu[0];
            }
            $s = trim((string) (data_get($p, 'Status') ?: data_get($bu, 'Status') ?: '(vacio)'));
            $st[$s] = ($st[$s] ?? 0) + 1;
        }
        arsort($st);
        foreach ($st as $s => $c) {
            $this->line("  {$s}: {$c}");
        }
    }

    private function imagenesYPrecios(array $products): void
    {
        $this->newLine();
        $this->line('-- IMAGENES Y PRECIOS --');
        $sinImagen = $sinPrecio = $conOferta = 0;
        $imgs = [];
        foreach ($products as $p) {
            $raw = data_get($p, 'Images.Image', []);
            if (is_string($raw)) {
                $raw = [$raw];
            }
            $n = count(array_filter((array) $raw));
            $imgs[] = $n;
            if ($n === 0 && trim((string) data_get($p, 'MainImage', '')) === '') {
                $sinImagen++;
            }

            $bu = data_get($p, 'BusinessUnits.BusinessUnit');
            if (isset($bu[0])) {
                $bu = $bu[0];
            }
            $price   = (float) (data_get($bu, 'Price') ?: 0);
            $special = (float) (data_get($bu, 'SpecialPrice') ?: 0);
            if ($price <= 0 && $special <= 0) {
                $sinPrecio++;
            }
            if ($special > 0 && $special < $price) {
                $conOferta++;
            }
        }
        $this->line('  imagenes por producto: min ' . min($imgs) . ', max ' . max($imgs) . ', promedio ' . round(array_sum($imgs) / count($imgs), 1));
        $this->line("  productos SIN ninguna imagen: {$sinImagen}");
        $this->line("  productos SIN precio (Price y SpecialPrice en 0): {$sinPrecio}");
        $this->line("  productos con oferta vigente declarada: {$conOferta}");
    }

    private function dump(array $products): void
    {
        $path = $this->option('dump');
        if (!$path) {
            $this->newLine();
            $this->line('(usa --dump=ruta.json para guardar 2 productos completos y ver la estructura exacta)');
            return;
        }

        // Preferimos un producto que parezca tener variantes.
        $porProducto = [];
        foreach ($products as $i => $p) {
            $pid = trim((string) data_get($p, 'ProductId', ''));
            if ($pid !== '') {
                $porProducto[$pid][] = $i;
            }
        }
        $grupo = array_values(array_filter($porProducto, fn($g) => count($g) > 1))[0] ?? null;
        $elegidos = $grupo
            ? array_map(fn($i) => $products[$i], array_slice($grupo, 0, 2))
            : array_slice($products, 0, 2);

        file_put_contents($path, json_encode($elegidos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->newLine();
        $this->info("Guardados 2 productos completos en {$path}");
    }
}
