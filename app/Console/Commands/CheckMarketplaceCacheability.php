<?php

namespace App\Console\Commands;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Comprueba que las paginas publicas del marketplace son IDENTICAS para dos
 * visitantes anonimos distintos.
 *
 * Es la condicion para poder cachearlas. Si una pagina trae algo propio del
 * visitante —el token CSRF, su historial de busqueda, su nombre— y se cachea,
 * una cache compartida acabaria sirviendo ESO a otra persona. El fallo no es
 * «va lento», es ensenar los datos de alguien a quien no le corresponden, asi
 * que conviene comprobarlo con una maquina y no de memoria.
 *
 * Cuatro visitantes, porque cada uno destapa una personalizacion distinta:
 *   A y B — recien llegados, sin nada previo.
 *   C     — ya busco algo (la sesion lleva afinidad de busqueda).
 *   D     — ya vio un producto (la sesion lleva "vistos recientemente").
 *
 * El perfil D se anadio despues de que la primera version del comando diera
 * por bueno algo que no lo era: solo probaba buscar, asi que no se enteraba de
 * que el bloque "Vistos recientemente" tambien sale distinto para cada
 * visitante anonimo. Si aparece otra personalizacion, el perfil que la
 * destape se anade AQUI — un guardian que no cubre un caso es peor que no
 * tenerlo, porque da luz verde en falso.
 *
 * Uso:
 *   php artisan marketplace:cache-check
 *   php artisan marketplace:cache-check --base=https://ebaemy.com --show=20
 */
class CheckMarketplaceCacheability extends Command
{
    protected $signature = 'marketplace:cache-check
                            {--base= : URL base a comprobar (por defecto APP_URL)}
                            {--ruta=* : Rutas a comprobar; por defecto las publicas del marketplace}
                            {--buscar=planta : Termino que teclea el visitante C para generar afinidad}
                            {--show=12 : Cuantas lineas distintas mostrar por ruta}';

    protected $description = 'Verifica que el HTML del marketplace es igual para cualquier visitante anonimo';

    private const RUTAS = ['/marketplace', '/marketplace/favoritos'];

    public function handle(): int
    {
        $base   = rtrim($this->option('base') ?: config('app.url'), '/');
        $rutas  = $this->option('ruta') ?: self::RUTAS;
        $show   = max(1, (int) $this->option('show'));
        $fallos = 0;

        $this->line("Base: {$base}");

        foreach ($rutas as $ruta) {
            $this->newLine();
            $this->line("── {$ruta}");

            $a = $this->visitante($base, [$ruta]);
            $b = $this->visitante($base, [$ruta]);
            // C busca algo primero: asi su sesion lleva afinidad al volver.
            $c = $this->visitante($base, [
                $ruta . '?q=' . urlencode((string) $this->option('buscar')),
                $ruta,
            ]);
            // D pasa por la ficha de un producto: asi su sesion lleva
            // "vistos recientemente" al volver.
            $ficha = $a !== null ? $this->unaFicha($a) : null;
            $d = $ficha ? $this->visitante($base, [$ficha, $ruta]) : null;

            if ($a === null || $b === null || $c === null) {
                $this->error('  no se pudo descargar la pagina');
                $fallos++;
                continue;
            }

            $fallos += $this->comparar('dos visitantes nuevos', $a, $b, $show);
            $fallos += $this->comparar('nuevo vs uno que ya busco', $a, $c, $show);
            if ($d !== null) {
                $fallos += $this->comparar('nuevo vs uno que ya vio un producto', $a, $d, $show);
            } else {
                $this->warn('  (sin ficha de producto en la pagina: no se pudo probar el perfil que ya vio algo)');
            }
        }

        $this->newLine();
        if ($fallos === 0) {
            $this->info('Todas las rutas son identicas entre visitantes anonimos: se pueden cachear.');

            return self::SUCCESS;
        }

        $this->error("{$fallos} comparacion(es) con diferencias: NO activar la cache todavia.");

        return self::FAILURE;
    }

    /** Primera ficha de producto enlazada en la pagina, para el perfil D. */
    private function unaFicha(string $html): ?string
    {
        return preg_match('#/marketplace/item/([a-z0-9][a-z0-9-]*)#i', $html, $m)
            ? '/marketplace/item/' . $m[1]
            : null;
    }

    /**
     * Recorre las URLs con una sesion propia (cookie jar aislado) y devuelve
     * el HTML de la ultima. Visitar antes otras URLs es lo que permite que la
     * sesion acumule estado, igual que un visitante de verdad.
     */
    private function visitante(string $base, array $rutas): ?string
    {
        $jar  = new CookieJar();
        $html = null;

        foreach ($rutas as $ruta) {
            try {
                $r = Http::withOptions(['cookies' => $jar, 'allow_redirects' => true])
                    ->timeout(45)
                    ->get($base . $ruta);
            } catch (\Throwable $e) {
                $this->warn('  ' . $ruta . ': ' . $e->getMessage());

                return null;
            }
            if (!$r->successful()) {
                $this->warn("  {$ruta}: HTTP {$r->status()}");

                return null;
            }
            $html = $r->body();
        }

        return $html;
    }

    /** @return int 0 si son iguales, 1 si no */
    private function comparar(string $etiqueta, string $x, string $y, int $show): int
    {
        if ($x === $y) {
            $this->info("  ✓ {$etiqueta}: identicas (" . number_format(strlen($x) / 1024, 1) . ' KB)');

            return 0;
        }

        // Se parte por '>' para que cada etiqueta HTML caiga en su linea y el
        // diff senale el trozo concreto en vez de "toda la pagina".
        $lx = explode('>', $x);
        $ly = explode('>', $y);
        $distintas = [];
        foreach (array_keys($lx + $ly) as $i) {
            $a = $lx[$i] ?? '(falta)';
            $b = $ly[$i] ?? '(falta)';
            if ($a !== $b) $distintas[] = [$a, $b];
        }

        $this->error(sprintf('  ✗ %s: %d trozos distintos', $etiqueta, count($distintas)));
        foreach (array_slice($distintas, 0, $show) as [$a, $b]) {
            $this->line('      - ' . trim(mb_substr($a, 0, 110)));
            $this->line('      + ' . trim(mb_substr($b, 0, 110)));
        }
        if (count($distintas) > $show) {
            $this->line('      … y ' . (count($distintas) - $show) . ' mas');
        }

        return 1;
    }
}
