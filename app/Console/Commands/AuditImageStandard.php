<?php

namespace App\Console\Commands;

use App\Services\Tenant\ImageProcessingService;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * El estándar de imagen de EBAEMY, hecho comprobable.
 *
 * Define lo que tiene que cumplir cualquier imagen de producto que viva en el
 * sistema, y lo verifica contra lo que hay de verdad en disco. Sin esto el
 * estándar es una frase en un documento: se respeta mientras alguien se
 * acuerde, y el día que un formulario nuevo se salte el pipeline nadie se
 * entera hasta que la tienda va lenta.
 *
 *   php artisan images:audit                      informe de todos los tenants
 *   php artisan images:audit --tenant=uuid        uno solo
 *   php artisan images:audit --json               salida para un agente
 *   php artisan images:audit --fix                regenera lo que incumple
 *   php artisan images:audit --limit=200          corta el recorrido
 *
 * NO borra nada nunca. `--fix` solo REGENERA a partir de la imagen principal,
 * que es la única que no se puede reconstruir.
 */
class AuditImageStandard extends Command
{
    protected $signature = 'images:audit
                            {--tenant= : UUID de un website específico}
                            {--fix : Regenera las variantes que falten o incumplan}
                            {--json : Salida JSON, para consumo automático}
                            {--limit=0 : Máximo de productos por tenant (0 = todos)}';

    protected $description = 'Comprueba que las imágenes de productos cumplan el estándar de EBAEMY';

    /**
     * EL ESTÁNDAR. Un solo sitio: si cambia, cambia aquí y el informe lo
     * refleja sin tocar nada más.
     *
     * Los números no son inventados: salen de `ImageProcessingService`, que es
     * quien las genera. Este comando comprueba lo que ese servicio promete.
     */
    private const PESO_MAX_PRINCIPAL  = ImageProcessingService::TARGET_MAX_BYTES; // 300 KB
    private const ANCHO_MAX_PRINCIPAL = 1200;
    private const PESO_MAX_VARIANTE   = 400 * 1024;

    /** Las versiones que todo producto publicado tiene que tener. */
    private const VARIANTES = [
        '_medium' => 'catálogo del panel',
        '_small'  => 'miniaturas',
        '_mp'     => 'marketplace (1080 cuadrado)',
        '_mobile' => 'tienda en el celular',
    ];

    private const EXTENSIONES_VALIDAS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];

    public function handle(): int
    {
        $uuid  = $this->option('tenant');
        $fix   = (bool) $this->option('fix');
        $json  = (bool) $this->option('json');
        $limit = (int) $this->option('limit');

        $websites = $uuid ? Website::where('uuid', $uuid)->get() : Website::all();

        if ($websites->isEmpty()) {
            $this->error('No se encontraron tenants.');

            return self::FAILURE;
        }

        $env    = app(Environment::class);
        $global = [
            'tenants' => [],
            'resumen' => [
                'productos' => 0, 'con_imagen' => 0, 'cumplen' => 0,
                'incumplen' => 0, 'reparados' => 0,
            ],
        ];

        foreach ($websites as $website) {
            $env->tenant($website);

            if (!Schema::connection('tenant')->hasTable('items')) {
                continue;
            }

            $informe = $this->auditarTenant($website->uuid, $fix, $limit);

            if ($informe['con_imagen'] === 0) {
                continue;
            }

            $global['tenants'][] = $informe;
            foreach (['productos', 'con_imagen', 'cumplen', 'incumplen', 'reparados'] as $k) {
                $global['resumen'][$k] += $informe[$k];
            }

            if (!$json) {
                $this->pintarTenant($informe);
            }
        }

        if ($json) {
            $this->line(json_encode($global, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $global['resumen']['incumplen'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->pintarResumen($global['resumen'], $fix);

        // Código de salida distinto de cero cuando algo incumple: así un cron o
        // un agente puede encadenar sin leer el texto.
        return $global['resumen']['incumplen'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function auditarTenant(string $uuid, bool $fix, int $limit): array
    {
        $disk = Storage::disk(ImageProcessingService::disk());
        $base = ImageProcessingService::BASE_DIR;

        $query = DB::connection('tenant')->table('items')
            ->select('id', 'description', 'image')
            ->whereNotNull('image')
            ->where('image', '<>', '')
            ->where('image', '<>', 'imagen-no-disponible.jpg');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $informe = [
            'tenant'     => $uuid,
            'productos'  => DB::connection('tenant')->table('items')->count(),
            'con_imagen' => 0,
            'cumplen'    => 0,
            'incumplen'  => 0,
            'reparados'  => 0,
            'motivos'    => [],
            'ejemplos'   => [],
        ];

        foreach ($query->get() as $item) {
            $informe['con_imagen']++;
            $fallos = $this->revisar($disk, $base, $item->image);

            if (empty($fallos)) {
                $informe['cumplen']++;
                continue;
            }

            $informe['incumplen']++;
            foreach ($fallos as $f) {
                $informe['motivos'][$f] = ($informe['motivos'][$f] ?? 0) + 1;
            }

            if (count($informe['ejemplos']) < 5) {
                $informe['ejemplos'][] = [
                    'item'   => $item->id,
                    'nombre' => mb_substr((string) $item->description, 0, 40),
                    'imagen' => $item->image,
                    'fallos' => $fallos,
                ];
            }

            if ($fix && $this->reparar($disk, $base, $item->image)) {
                $informe['reparados']++;
            }
        }

        return $informe;
    }

    /**
     * Qué incumple esta imagen. Devuelve los motivos en texto, que es lo que se
     * cuenta y se enseña: "faltan 3 variantes" no dice qué hacer.
     */
    private function revisar($disk, string $base, string $filename): array
    {
        $fallos = [];
        $ruta   = $base . '/' . $filename;

        if (!$disk->exists($ruta)) {
            return ['la imagen principal no está en disco'];
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONES_VALIDAS, true)) {
            $fallos[] = 'formato fuera del estándar (.' . $ext . ')';
        }

        if ((int) $disk->size($ruta) > self::PESO_MAX_PRINCIPAL) {
            $fallos[] = 'la principal pesa más de ' . $this->kb(self::PESO_MAX_PRINCIPAL);
        }

        // El ancho solo se puede medir con el archivo en local; en un disco
        // remoto se omite en vez de descargar 17.000 imágenes.
        if (ImageProcessingService::disk() === 'public') {
            $medidas = @getimagesize($disk->path($ruta));
            if ($medidas === false) {
                $fallos[] = 'el archivo no se puede leer como imagen';
            } elseif ($medidas[0] > self::ANCHO_MAX_PRINCIPAL) {
                $fallos[] = 'la principal mide más de ' . self::ANCHO_MAX_PRINCIPAL . 'px de ancho';
            }
        }

        foreach (self::VARIANTES as $sufijo => $para) {
            $vr = $this->rutaVariante($disk, $base, $filename, $sufijo);

            if ($vr === null) {
                $fallos[] = 'falta la versión ' . $sufijo . ' (' . $para . ')';
                continue;
            }

            if ((int) $disk->size($vr) > self::PESO_MAX_VARIANTE) {
                $fallos[] = 'la versión ' . $sufijo . ' pesa más de ' . $this->kb(self::PESO_MAX_VARIANTE);
            }
        }

        return $fallos;
    }

    /**
     * La ruta de una variante, o null si de verdad no existe.
     *
     * Mira las DOS nomenclaturas. La actual usa guion bajo (`_medium`), pero
     * un generador anterior dejo archivos con guion (`-medium`) y siguen en
     * disco y en uso. Buscando solo la nueva, el auditor daba por «faltante»
     * una variante que estaba ahi: verificado en ebaemy_myka, que aparecia
     * incumpliendo por `_medium` y `_small` mientras tenia sus
     * `-medium.jpg` y `-small.jpg` generados el mismo dia que la imagen.
     *
     * Un falso positivo aqui es caro: manda a reparar lo que no esta roto y
     * deja la cifra de incumplimientos sin bajar nunca, por mucho que se
     * ejecute --fix.
     */
    private function rutaVariante($disk, string $base, string $filename, string $sufijo): ?string
    {
        $candidatas = [
            ImageProcessingService::variantFilename($filename, $sufijo),
            // Nomenclatura antigua: el mismo sufijo con guion.
            ImageProcessingService::variantFilename($filename, '-' . ltrim($sufijo, '_')),
        ];

        foreach ($candidatas as $nombre) {
            $ruta = $base . '/' . $nombre;

            if ($disk->exists($ruta)) {
                return $ruta;
            }
        }

        return null;
    }

    /**
     * Regenera todo a partir de la principal.
     *
     * OJO con el alcance real: el pipeline crea las versiones que falten y
     * sus WebP, pero **no reescribe el archivo principal**. Comprobado el
     * 2026-10-03 en ebaemy_makingroup: tras --fix, la principal seguia
     * pesando 312 KB y conservaba su fecha de 2025, mientras todos los WebP
     * se habian regenerado.
     *
     * Por eso un incumplimiento de «la principal pesa más de X» no se corrige
     * con --fix por mucho que se repita. Para eso hace falta recomprimir el
     * original, que es una decision aparte: degrada la imagen que vera el
     * comprador y es irreversible.
     */
    private function reparar($disk, string $base, string $filename): bool
    {
        $ruta = $base . '/' . $filename;

        if (!$disk->exists($ruta)) {
            return false;   // sin principal no hay nada que reconstruir
        }

        try {
            $temp = tempnam(sys_get_temp_dir(), 'audit_');
            file_put_contents($temp, $disk->get($ruta));

            ImageProcessingService::processAndStore($temp, pathinfo($filename, PATHINFO_FILENAME));
            @unlink($temp);

            return true;
        } catch (\Throwable $e) {
            $this->warn('  no se pudo regenerar ' . $filename . ': ' . $e->getMessage());

            return false;
        }
    }

    private function pintarTenant(array $i): void
    {
        $estado = $i['incumplen'] === 0
            ? '<info>OK</info>'
            : '<comment>' . $i['incumplen'] . ' incumplen</comment>';

        $this->line(sprintf(
            '%-30s %4d con imagen · %4d cumplen · %s',
            $i['tenant'], $i['con_imagen'], $i['cumplen'], $estado
        ));

        arsort($i['motivos']);
        foreach ($i['motivos'] as $motivo => $n) {
            $this->line(sprintf('      %4d × %s', $n, $motivo));
        }

        if ($i['reparados'] > 0) {
            $this->info('      ' . $i['reparados'] . ' regeneradas');
        }
    }

    private function pintarResumen(array $r, bool $fix): void
    {
        $this->newLine();
        $this->line('─────────────────────────────────────────────');
        $this->line(sprintf('Productos con imagen: %d', $r['con_imagen']));
        $this->line(sprintf('Cumplen el estándar : %d', $r['cumplen']));
        $this->line(sprintf('Incumplen           : %d', $r['incumplen']));

        if ($fix) {
            $this->line(sprintf('Regeneradas         : %d', $r['reparados']));
            $this->newLine();
            $this->comment('«Regeneradas» son las versiones que faltaban y sus WebP.');
            $this->comment('El archivo principal NO se reescribe, así que un «pesa más de');
            $this->comment('300 KB» o «mide más de 1200px» seguirá apareciendo tras --fix:');
            $this->comment('eso pide recomprimir el original, que degrada la foto y no tiene');
            $this->comment('vuelta atrás.');
        } elseif ($r['incumplen'] > 0) {
            $this->newLine();
            $this->comment('Para regenerar las versiones que falten: php artisan images:audit --fix');
        }
    }

    private function kb(int $bytes): string
    {
        return round($bytes / 1024) . ' KB';
    }
}
