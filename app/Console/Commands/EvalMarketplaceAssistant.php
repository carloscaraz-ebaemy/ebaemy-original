<?php

namespace App\Console\Commands;

use App\Services\Marketplace\ShoppingAssistant;
use App\Services\Marketplace\ShoppingAssistantTools;
use Illuminate\Console\Command;

/**
 * Evaluación del asistente de compras SIN gastar en la API.
 *
 * El bot solo puede recomendar lo que devuelve buscar_productos, así que la
 * mayor parte de su calidad es la del buscador. Este comando lee un juego de
 * casos (database/data/assistant_eval.json) y comprueba, caso por caso, que
 * la búsqueda devuelve lo esperado:
 *
 *   { "consulta": "arbol navidad", "espera": "alguno" }             → ≥ 1 resultado
 *   { "consulta": "xqzw", "espera": "ninguno" }                      → 0 resultados
 *   { "consulta": "pino americano", "espera": "pino-americano-" }    → un slug que contenga eso
 *   { "consulta": "cojin", "precio_max": 50, "espera": "alguno" }
 *
 * También comprueba que el recorte de sugerencias funciona. Se corre después
 * de tocar el buscador o el índice:
 *
 *   php artisan marketplace:assistant-eval
 *   php artisan marketplace:assistant-eval --archivo=/ruta/otros_casos.json
 *
 * Sale con código 1 si algún caso falla, para poder encadenarlo.
 */
class EvalMarketplaceAssistant extends Command
{
    protected $signature = 'marketplace:assistant-eval {--archivo= : JSON con los casos (por defecto database/data/assistant_eval.json)}';

    protected $description = 'Comprueba que las herramientas del asistente de compras devuelven lo esperado (sin llamar a la API)';

    public function handle(): int
    {
        $ruta = $this->option('archivo') ?: database_path('data/assistant_eval.json');
        if (!is_file($ruta)) {
            $this->error("No existe {$ruta}");
            return self::FAILURE;
        }
        $casos = json_decode((string) file_get_contents($ruta), true);
        if (!is_array($casos)) {
            $this->error('El JSON no es una lista de casos.');
            return self::FAILURE;
        }

        $fallos = 0;
        $filas = [];
        foreach ($casos as $c) {
            $tools = new ShoppingAssistantTools();
            $r = $tools->buscar(
                (string) ($c['consulta'] ?? ''),
                isset($c['precio_min']) ? (float) $c['precio_min'] : null,
                isset($c['precio_max']) ? (float) $c['precio_max'] : null,
                $c['tienda'] ?? null,
                !empty($c['solo_ofertas']),
                (string) ($c['orden'] ?? 'relevancia')
            );
            $slugs = array_column($r['productos'] ?? [], 'slug');
            $espera = (string) ($c['espera'] ?? 'alguno');

            $ok = match (true) {
                $espera === 'alguno'  => count($slugs) > 0,
                $espera === 'ninguno' => count($slugs) === 0,
                default               => (bool) array_filter($slugs, fn ($s) => str_contains($s, $espera)),
            };
            if (!$ok) $fallos++;

            $filas[] = [
                $ok ? '✔' : '✘',
                $c['consulta'] ?? '',
                $espera,
                count($slugs) . (!empty($r['parecidos']) ? ' (parecidos)' : ''),
                implode(', ', array_slice($slugs, 0, 3)),
            ];
        }

        $this->table(['', 'Consulta', 'Espera', 'Resultados', 'Primeros slugs'], $filas);

        // El recorte de la línea de sugerencias no depende de la BD.
        [$texto, $sug] = ShoppingAssistant::splitSuggestions("Te recomiendo el cojín gris.\n[[sugerencias]] Menos de S/ 50 | Ver colores | Agrégalo");
        if ($texto !== 'Te recomiendo el cojín gris.' || $sug !== ['Menos de S/ 50', 'Ver colores', 'Agrégalo']) {
            $this->error('splitSuggestions no recorta bien la última línea.');
            $fallos++;
        }

        $this->line(sprintf('%d casos, %d fallos.', count($casos), $fallos));

        return $fallos === 0 ? self::SUCCESS : self::FAILURE;
    }
}
