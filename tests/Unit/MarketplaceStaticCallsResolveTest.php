<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Toda llamada estática `Clase::metodo()` de los controladores y servicios
 * del marketplace debe resolver a una clase que exista.
 *
 * Por qué: el 2026-11-17 el checkout llamó a `MarketplaceCheckoutRules::…`
 * sin `use` y PHP buscó la clase en el namespace del controlador. Todo POST
 * /marketplace/checkout dio 500 durante ocho días y ningún test lo vio,
 * porque el test unitario de las reglas sí importaba la clase. Este test
 * lee el código fuente y lo detecta. Extiende el TestCase de Laravel para
 * que los alias de raíz (\DB, \Log, \Cache) existan.
 */
class MarketplaceStaticCallsResolveTest extends TestCase
{
    private const DIRS = [
        'app/Http/Controllers',
        'app/Http/Controllers/Marketplace',
        'app/Services/System',
        'app/Services/Marketplace',
        'app/Jobs/Marketplace',
    ];

    public function test_static_calls_resolve_to_existing_classes(): void
    {
        $base = dirname(__DIR__, 2);
        $fallos = [];

        foreach (self::DIRS as $dir) {
            foreach (glob("{$base}/{$dir}/*Marketplace*.php") ?: [] as $file) {
                foreach ($this->unresolved($file) as $nombre) {
                    $fallos[] = str_replace($base . '/', '', $file) . " → {$nombre}";
                }
            }
        }

        $this->assertSame([], $fallos, "Clases que no existen:\n" . implode("\n", $fallos));
    }

    /** @return string[] nombres de clase usados como `X::` que no resuelven */
    private function unresolved(string $file): array
    {
        $src = (string) file_get_contents($file);
        $tokens = token_get_all($src);

        $namespace = '';
        $uses = [];
        $n = count($tokens);

        // namespace y use (solo los de nivel de archivo, antes de la clase)
        for ($i = 0; $i < $n; $i++) {
            $t = $tokens[$i];
            if (!is_array($t)) continue;
            if ($t[0] === T_NAMESPACE) {
                $namespace = $this->readName($tokens, $i + 1);
            } elseif ($t[0] === T_USE) {
                $full = $this->readName($tokens, $i + 1, $alias);
                if ($full !== '' && !str_contains($full, '(')) {
                    $uses[$alias ?: substr($full, strrpos($full, '\\') + 1)] = $full;
                }
            } elseif ($t[0] === T_CLASS) {
                break;
            }
        }

        $out = [];
        for ($i = 0; $i < $n - 1; $i++) {
            $t = $tokens[$i];
            if (!is_array($t) || !in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) continue;
            // siguiente token significativo debe ser ::
            $j = $i + 1;
            while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
            if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_DOUBLE_COLON) continue;

            $name = $t[1];
            if (in_array(strtolower($name), ['self', 'static', 'parent'], true)) continue;

            $fqcn = match (true) {
                str_starts_with($name, '\\')   => ltrim($name, '\\'),
                isset($uses[$name])            => $uses[$name],
                str_contains($name, '\\')      => (isset($uses[strtok($name, '\\')])
                                                    ? $uses[strtok($name, '\\')] . substr($name, strpos($name, '\\'))
                                                    : $namespace . '\\' . $name),
                default                        => $namespace . '\\' . $name,
            };

            if (!class_exists($fqcn) && !interface_exists($fqcn) && !enum_exists($fqcn) && !trait_exists($fqcn)) {
                $out[$name] = $name . " (buscado como {$fqcn})";
            }
        }

        return array_values($out);
    }

    private function readName(array $tokens, int $i, ?string &$alias = null): string
    {
        $alias = null;
        $name = '';
        $n = count($tokens);
        for (; $i < $n; $i++) {
            $t = $tokens[$i];
            if ($t === ';' || $t === '{') break;
            if (!is_array($t)) { if ($t === ',') break; continue; }
            if (in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                if ($alias !== null) $alias = $t[1]; else $name .= $t[1];
            } elseif ($t[0] === T_AS) {
                $alias = '';
            }
        }
        return trim($name, '\\');
    }
}
