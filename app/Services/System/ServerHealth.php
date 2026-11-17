<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Estado del servidor para el SuperAdmin (/status/servidor): disco, memoria,
 * CPU, bases de datos, colas, límites de PHP y si el sistema está listo para
 * recibir videos.
 *
 * Solo LEE. Lo caro (tamaño de carpetas con `du`, tamaño de las bases) se
 * cachea 10 minutos; lo barato (memoria, carga) se mide en cada visita.
 * Pensado para Linux (/proc); en otro sistema esos bloques salen vacíos en
 * vez de fallar.
 */
class ServerHealth
{
    /** Peso de referencia de un video corto de producto (1 min, 1080p, de celular). */
    public const VIDEO_MB_REFERENCIA = 60;

    public function snapshot(): array
    {
        $disco = $this->disk();

        return [
            'generado'  => now(),
            'disco'     => $disco,
            'carpetas'  => Cache::remember('server_health_dirs_v1', 600, fn () => $this->directories()),
            'memoria'   => $this->memory(),
            'cpu'       => $this->cpu(),
            'bases'     => Cache::remember('server_health_dbs_v1', 600, fn () => $this->databases()),
            'colas'     => $this->queues(),
            'php'       => $this->phpLimits(),
            'servicios' => $this->services(),
            'video'     => $this->videoReadiness($disco),
        ];
    }

    public function disk(): array
    {
        $ruta = base_path();
        $total = @disk_total_space($ruta) ?: 0;
        $libre = @disk_free_space($ruta) ?: 0;
        $usado = max(0, $total - $libre);

        return [
            'total' => $total,
            'usado' => $usado,
            'libre' => $libre,
            'pct'   => $total > 0 ? round($usado / $total * 100, 1) : null,
        ];
    }

    /** Lo que ocupa cada carpeta que crece con el uso. */
    public function directories(): array
    {
        $carpetas = [
            'Fotos y archivos subidos' => storage_path('app/public/uploads'),
            'Logos de tiendas'         => storage_path('app/public/seller-logos'),
            'Logs'                     => storage_path('logs'),
            'Backups'                  => base_path('backups'),
            'Caché de archivos'        => storage_path('framework/cache'),
        ];

        $out = [];
        foreach ($carpetas as $nombre => $ruta) {
            $out[] = ['nombre' => $nombre, 'ruta' => $ruta, 'bytes' => $this->dirSize($ruta)];
        }

        return $out;
    }

    public function memory(): array
    {
        $info = $this->procMeminfo();
        if (!$info) {
            return [];
        }

        $total = ($info['MemTotal'] ?? 0) * 1024;
        $disponible = ($info['MemAvailable'] ?? 0) * 1024;
        $swapTotal = ($info['SwapTotal'] ?? 0) * 1024;
        $swapUsado = $swapTotal - (($info['SwapFree'] ?? 0) * 1024);

        return [
            'total'       => $total,
            'disponible'  => $disponible,
            'usado'       => $total - $disponible,
            'pct'         => $total > 0 ? round(($total - $disponible) / $total * 100, 1) : null,
            'swap_total'  => $swapTotal,
            'swap_usado'  => max(0, $swapUsado),
            'swap_pct'    => $swapTotal > 0 ? round(max(0, $swapUsado) / $swapTotal * 100, 1) : null,
        ];
    }

    public function cpu(): array
    {
        $nucleos = 0;
        if (is_readable('/proc/cpuinfo')) {
            $nucleos = substr_count((string) @file_get_contents('/proc/cpuinfo'), "\nprocessor") + 1;
        }
        $carga = function_exists('sys_getloadavg') ? (sys_getloadavg() ?: [null, null, null]) : [null, null, null];

        $uptime = null;
        if (is_readable('/proc/uptime')) {
            $uptime = (int) explode(' ', (string) @file_get_contents('/proc/uptime'))[0];
        }

        return [
            'nucleos'   => $nucleos ?: null,
            'carga_1'   => $carga[0] !== null ? round($carga[0], 2) : null,
            'carga_5'   => $carga[1] !== null ? round($carga[1], 2) : null,
            'carga_15'  => $carga[2] !== null ? round($carga[2], 2) : null,
            // Carga de 5 min / núcleos: por encima de 1 hay procesos esperando CPU.
            'uso_pct'   => ($nucleos && $carga[1] !== null) ? round($carga[1] / $nucleos * 100) : null,
            'uptime_s'  => $uptime,
        ];
    }

    /** Las 8 bases más grandes y el total (sistema + tenants + warehouse). */
    public function databases(): array
    {
        try {
            $filas = DB::connection('system')->select(
                'SELECT table_schema AS nombre, SUM(data_length + index_length) AS bytes, COUNT(*) AS tablas
                   FROM information_schema.tables
                  WHERE table_schema NOT IN ("mysql", "sys", "performance_schema", "information_schema")
               GROUP BY table_schema
               ORDER BY bytes DESC'
            );
        } catch (\Throwable $e) {
            return ['total' => null, 'cantidad' => null, 'top' => []];
        }

        return [
            'total'    => array_sum(array_map(fn ($f) => (int) $f->bytes, $filas)),
            'cantidad' => count($filas),
            'top'      => array_map(fn ($f) => [
                'nombre' => $f->nombre,
                'bytes'  => (int) $f->bytes,
                'tablas' => (int) $f->tablas,
            ], array_slice($filas, 0, 8)),
        ];
    }

    public function queues(): array
    {
        $pendientes = $fallidos = $fallidos24h = null;
        try {
            $pendientes = DB::connection('system')->table('jobs')->count();
        } catch (\Throwable $e) {
        }
        try {
            $fallidos = DB::connection('system')->table('failed_jobs')->count();
            $fallidos24h = DB::connection('system')->table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())->count();
        } catch (\Throwable $e) {
        }

        return [
            'conexion'     => config('queue.default'),
            'workers'      => $this->countProcesses('queue:work'),
            'pendientes'   => $pendientes,
            'fallidos'     => $fallidos,
            'fallidos_24h' => $fallidos24h,
        ];
    }

    /** Los límites del PHP que atiende la web (php-fpm), no los de la consola. */
    public function phpLimits(): array
    {
        return [
            'version'             => PHP_VERSION,
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size'       => ini_get('post_max_size'),
            'memory_limit'        => ini_get('memory_limit'),
            'max_execution_time'  => ini_get('max_execution_time'),
            'subida_max_bytes'    => min(
                $this->iniBytes(ini_get('upload_max_filesize')),
                $this->iniBytes(ini_get('post_max_size'))
            ),
        ];
    }

    public function services(): array
    {
        return [
            'cache'          => config('cache.default'),
            'sesiones'       => config('session.driver'),
            'nube_medios'    => (bool) (config('filesystems.disks.media.bucket') && config('filesystems.disks.media.key')),
            'ffmpeg'         => $this->hasBinary('ffmpeg'),
        ];
    }

    /**
     * ¿Se pueden subir videos hoy? Cada punto es una condición que, si falla,
     * rompe o pone en riesgo la subida o la reproducción de videos.
     */
    public function videoReadiness(?array $disco = null): array
    {
        $disco ??= $this->disk();
        $php = $this->phpLimits();
        $mem = $this->memory();
        $srv = $this->services();
        $refBytes = self::VIDEO_MB_REFERENCIA * 1024 * 1024;

        $checks = [
            [
                'ok'      => $php['subida_max_bytes'] >= $refBytes,
                'titulo'  => 'Tamaño máximo de subida',
                'detalle' => 'Hoy se aceptan archivos de hasta ' . $this->human($php['subida_max_bytes'])
                    . '. Un video de 1 minuto grabado con el celular pesa unos ' . self::VIDEO_MB_REFERENCIA . ' MB.',
            ],
            [
                'ok'      => $srv['nube_medios'],
                'titulo'  => 'Almacenamiento en la nube',
                'detalle' => $srv['nube_medios']
                    ? 'Hay un bucket configurado para medios.'
                    : 'Los archivos se guardan en el disco de este servidor. Los videos lo llenarían rápido y se servirían desde la misma máquina que atiende las tiendas.',
            ],
            [
                'ok'      => $srv['ffmpeg'],
                'titulo'  => 'Procesador de video (ffmpeg)',
                'detalle' => $srv['ffmpeg']
                    ? 'Instalado: se pueden comprimir videos y sacar miniaturas.'
                    : 'No está instalado: no se pueden comprimir los videos ni sacarles una miniatura.',
            ],
            [
                'ok'      => ($mem['disponible'] ?? 0) >= 1.5 * 1024 ** 3 && ($mem['swap_pct'] ?? 0) < 25,
                'titulo'  => 'Memoria para procesar video',
                'detalle' => 'Disponibles ' . $this->human($mem['disponible'] ?? 0) . ' de ' . $this->human($mem['total'] ?? 0)
                    . ', y ' . $this->human($mem['swap_usado'] ?? 0) . ' en swap. Comprimir un video en este servidor competiría con las tiendas.',
            ],
            [
                'ok'      => ($disco['pct'] ?? 100) < 70,
                'titulo'  => 'Espacio en disco',
                'detalle' => 'Quedan ' . $this->human($disco['libre'] ?? 0) . ' libres ('
                    . (100 - ($disco['pct'] ?? 100)) . ' %).',
            ],
        ];

        $libresParaVideos = max(0, ($disco['libre'] ?? 0) - 0.2 * ($disco['total'] ?? 0));

        return [
            'listo'      => !in_array(false, array_column($checks, 'ok'), true),
            'checks'     => $checks,
            // Videos que caben dejando un 20 % del disco libre como colchón.
            'caben'      => (int) floor($libresParaVideos / $refBytes),
            'ref_mb'     => self::VIDEO_MB_REFERENCIA,
        ];
    }

    public function human($bytes): string
    {
        $bytes = (float) $bytes;
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $i => $u) {
            if ($bytes < 1024 || $u === 'TB') {
                return ($i === 0 ? (int) $bytes : number_format($bytes, $bytes < 10 ? 1 : 0)) . ' ' . $u;
            }
            $bytes /= 1024;
        }
        return '';
    }

    private function dirSize(string $ruta): ?int
    {
        if (!is_dir($ruta)) {
            return null;
        }
        if (PHP_OS_FAMILY === 'Linux') {
            $salida = [];
            @exec('timeout 20 du -sb ' . escapeshellarg($ruta) . ' 2>/dev/null', $salida);
            if (isset($salida[0]) && preg_match('/^(\d+)/', $salida[0], $m)) {
                return (int) $m[1];
            }
            return null;
        }

        $total = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($ruta, \FilesystemIterator::SKIP_DOTS)) as $f) {
            $total += $f->getSize();
        }
        return $total;
    }

    private function procMeminfo(): array
    {
        if (!is_readable('/proc/meminfo')) {
            return [];
        }
        $out = [];
        foreach (file('/proc/meminfo') ?: [] as $linea) {
            if (preg_match('/^(\w+):\s+(\d+)/', $linea, $m)) {
                $out[$m[1]] = (int) $m[2];
            }
        }
        return $out;
    }

    private function countProcesses(string $patron): ?int
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return null;
        }
        $salida = [];
        @exec('pgrep -fc ' . escapeshellarg($patron) . ' 2>/dev/null', $salida);
        return isset($salida[0]) ? (int) $salida[0] : null;
    }

    private function hasBinary(string $nombre): bool
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return false;
        }
        $salida = [];
        @exec('command -v ' . escapeshellarg($nombre) . ' 2>/dev/null', $salida);
        return !empty($salida[0]);
    }

    private function iniBytes($valor): int
    {
        $valor = trim((string) $valor);
        if ($valor === '' || $valor === '-1') {
            return PHP_INT_MAX;
        }
        $n = (float) $valor;
        return (int) match (strtolower(substr($valor, -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }
}
