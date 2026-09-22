<?php

namespace App\Services\Security\Support;

use App\Services\Security\State\FileStateStore;

/**
 * Monitoreo de integridad de los archivos criticos del checkout y de pagos.
 *
 * Guarda el SHA-256 de cada archivo y compara en cada corrida. Un cambio que no
 * corresponda a un despliegue es la firma tipica de un skimmer de tarjetas
 * inyectado en el codigo.
 *
 * En la primera corrida solo aprende los hashes: no alerta.
 */
final class IntegrityMonitor
{
    private const NAMESPACE = 'integrity';

    public function __construct(private readonly FileStateStore $state) {}

    /**
     * @param  string[]  $files  Rutas relativas a la raiz del proyecto.
     * @param  string[]  $globs  Patrones glob relativos a la raiz.
     * @return array{changed:array<int,array>,added:array<int,string>,removed:array<int,string>,baseline:bool,total:int}
     */
    public function check(array $files, array $globs = []): array
    {
        $paths = $this->expand($files, $globs);

        $previous = (array) $this->state->get(self::NAMESPACE, 'hashes', []);
        $baseline = empty($previous);

        $current = [];
        $changed = [];
        $added   = [];

        foreach ($paths as $relative => $absolute) {
            $hash = @hash_file('sha256', $absolute);
            if ($hash === false) continue;

            $current[$relative] = [
                'sha256' => $hash,
                'size'   => (int) @filesize($absolute),
                'mtime'  => (int) @filemtime($absolute),
            ];

            if (!isset($previous[$relative])) {
                if (!$baseline) $added[] = $relative;
                continue;
            }

            if ($previous[$relative]['sha256'] !== $hash) {
                $changed[] = [
                    'file'       => $relative,
                    'sha256_old' => $previous[$relative]['sha256'],
                    'sha256_new' => $hash,
                    'mtime'      => date('c', $current[$relative]['mtime']),
                    'size_old'   => $previous[$relative]['size'] ?? null,
                    'size_new'   => $current[$relative]['size'],
                ];
            }
        }

        $removed = array_values(array_diff(array_keys($previous), array_keys($current)));
        if ($baseline) $removed = [];

        $this->state->put(self::NAMESPACE, 'hashes', $current);

        return [
            'changed'  => $changed,
            'added'    => $added,
            'removed'  => $removed,
            'baseline' => $baseline,
            'total'    => count($current),
        ];
    }

    /**
     * Marca los hashes actuales como legitimos. Se usa despues de un despliegue
     * para que el siguiente escaneo no reporte los cambios esperados:
     *     php artisan security:scan --accept-integrity
     */
    public function accept(array $files, array $globs = []): int
    {
        $paths   = $this->expand($files, $globs);
        $current = [];

        foreach ($paths as $relative => $absolute) {
            $hash = @hash_file('sha256', $absolute);
            if ($hash === false) continue;

            $current[$relative] = [
                'sha256' => $hash,
                'size'   => (int) @filesize($absolute),
                'mtime'  => (int) @filemtime($absolute),
            ];
        }

        $this->state->put(self::NAMESPACE, 'hashes', $current);

        return count($current);
    }

    /** @return array<string,string> relativo => absoluto */
    private function expand(array $files, array $globs): array
    {
        $root  = rtrim(base_path(), '/\\');
        $paths = [];

        foreach ($files as $relative) {
            $absolute = $root . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
            if (is_file($absolute)) {
                $paths[$relative] = $absolute;
            }
        }

        foreach ($globs as $glob) {
            foreach ((array) glob($root . DIRECTORY_SEPARATOR . ltrim($glob, '/\\')) as $match) {
                if (!is_file($match)) continue;
                $relative = str_replace('\\', '/', substr($match, strlen($root) + 1));
                $paths[$relative] = $match;
            }
        }

        return $paths;
    }
}
