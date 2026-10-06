<?php

namespace App\Console\Commands;

use App\Models\Tenant\Company;
use App\Models\Tenant\ConfigurationEcommerce;
use App\Models\Tenant\Item;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Modules\Item\Models\Category;

/**
 * Dice en que estado esta el SEO de cada tienda y, con --fix, rellena lo basico.
 *
 * El layout del storefront ya emite title, description, canonical, Open Graph,
 * Twitter y JSON-LD (Store + Organization). Lo que falta son los datos: cuando
 * `seo_title` esta vacio el layout cae a `Company::name`, que es la RAZON SOCIAL
 * del titular. Comprobado en produccion el 2026-10-03: de 5 tiendas, 4
 * publicaban el nombre del DNI como titulo en Google
 * («MONZON ANDERSON LIA AURORA») y las 4 compartian la misma descripcion de
 * fabrica, «Bienvenido a nuestra tienda.». Una descripcion repetida entre
 * dominios no describe nada y Google la sustituye por un trozo de la pagina.
 *
 * Lo que --fix escribe sale de datos reales del tenant (nombre comercial y sus
 * categorias con producto publicado), nunca inventado, y solo en los campos que
 * esten vacios: nunca pisa lo que el vendedor haya configurado.
 *
 * `google_site_verification` NO se rellena: es un token por propiedad que da
 * Search Console y hay que pegarlo a mano, uno por subdominio.
 *
 *   php artisan seo:tenant-audit
 *   php artisan seo:tenant-audit --fix
 *   php artisan seo:tenant-audit --fix --tenant=<uuid>
 */
class SeoTenantAudit extends Command
{
    protected $signature = 'seo:tenant-audit
        {--tenant= : UUID de un website concreto (por defecto: todos)}
        {--fix : Rellena los campos vacios en vez de solo informar}
        {--rewrite : Con --fix, rehace tambien las descripciones que genero este comando}';

    protected $description = 'Audita el SEO publicado por cada tienda y opcionalmente rellena lo que falta';

    /** Descripcion de fabrica del layout: cuenta como vacia. */
    private const DESC_FABRICA = 'Bienvenido a nuestra tienda.';

    public function handle(): int
    {
        $uuid    = $this->option('tenant');
        $fix     = (bool) $this->option('fix');
        $rewrite = (bool) $this->option('rewrite');

        $websites = $uuid ? Website::where('uuid', $uuid)->get() : Website::all();

        if ($websites->isEmpty()) {
            $this->error('No hay websites que revisar.');

            return self::FAILURE;
        }

        $env      = app(Environment::class);
        $previo   = $env->tenant();
        $filas    = [];
        $escritos = 0;
        $criticos = 0;

        foreach ($websites as $website) {
            $fqdn = optional($website->hostnames()->first())->fqdn;
            if (!$fqdn) {
                continue;
            }

            $env->tenant($website);

            try {
                $seo     = ConfigurationEcommerce::first();
                $company = Company::first();
            } catch (\Throwable $e) {
                $filas[] = [$fqdn, 'ERROR', substr($e->getMessage(), 0, 38), '', ''];
                continue;
            }

            if (!$company) {
                $filas[] = [$fqdn, 'sin company', '', '', ''];
                continue;
            }

            $comercial = trim((string) $company->trade_name);
            $legal     = trim((string) $company->name);

            // Lo que hoy sale en el <title> de verdad, con la misma cascada que
            // el layout: seo_title -> company.name. Ojo: el layout NO usa
            // trade_name para el titulo, asi que un nombre comercial bien puesto
            // tampoco salva a la tienda si seo_title esta vacio.
            $tituloActual = trim((string) ($seo->seo_title ?? '')) ?: $legal;
            $descActual   = trim((string) ($seo->seo_description ?? ''));

            $tituloEsLegal = $tituloActual === $legal && $legal !== '';
            $descGenerica  = $descActual === '' || $descActual === self::DESC_FABRICA;

            // Con --rewrite, lo que escribio este comando tambien se rehace.
            // Sirve para propagar una mejora de la plantilla sin tocar lo que
            // haya escrito a mano el vendedor, que es sagrado.
            if ($rewrite && !$descGenerica && $this->laEscribimosNosotros($descActual)) {
                $descGenerica = true;
            }

            $estado = 'ok';
            if ($tituloEsLegal || $descGenerica) {
                $estado = 'INCOMPLETO';
                $criticos++;
            }
            // Sin nombre comercial no hay nada con que sustituir la razon social:
            // «MONZON ANDERSON LIA AURORA - Tienda online» no mejora a
            // «MONZON ANDERSON LIA AURORA», solo publica el nombre del titular
            // con mas palabras. Se marca aparte porque lo tiene que resolver el
            // vendedor, no este comando.
            if ($tituloEsLegal && $comercial === '') {
                $estado = 'FALTA NOMBRE COMERCIAL';
            }
            if (!(bool) ($seo->indexable ?? true)) {
                $estado = 'noindex';
            }

            if (!$fix) {
                $filas[] = $this->fila($fqdn, $estado, $tituloActual, $seo);
                continue;
            }

            if (!$seo) {
                $seo = new ConfigurationEcommerce();
            }

            $cambios = [];
            $cats    = $descGenerica ? $this->categoriasConProducto(3) : '';

            // El titulo solo se escribe con nombre comercial. Nunca con la razon
            // social: es el nombre del titular y no es lo que la tienda quiere
            // que se lea en Google.
            if ($comercial !== '' && ($tituloEsLegal || trim((string) ($seo->seo_title ?? '')) === '')) {
                $cambios['seo_title'] = $this->recortar($comercial . ' - Tienda online', 60);
            }

            if ($descGenerica) {
                if ($comercial !== '') {
                    $cambios['seo_description'] = $this->describir($comercial, $cats);
                } elseif ($cats !== '') {
                    // Sin nombre comercial, la descripcion se construye solo con
                    // las categorias: sigue siendo distinta de la del vecino y no
                    // mete el nombre del titular donde no toca.
                    $cambios['seo_description'] = $this->recortar(
                        'Catálogo de ' . $cats . '. Compra online desde tu celular.',
                        155
                    );
                }
            }

            // og_* alimentan lo que se ve al compartir por WhatsApp. Si estan
            // vacios el layout ya cae a seo_*, pero dejarlos explicitos evita
            // que un cambio futuro del layout se los lleve por delante.
            if (isset($cambios['seo_title']) && trim((string) ($seo->og_title ?? '')) === '') {
                $cambios['og_title'] = $cambios['seo_title'];
            }
            if (isset($cambios['seo_description']) && trim((string) ($seo->og_description ?? '')) === '') {
                $cambios['og_description'] = $cambios['seo_description'];
            }

            if (!$cambios) {
                $filas[] = $this->fila($fqdn, $estado, $tituloActual, $seo);
                continue;
            }

            foreach ($cambios as $columna => $valor) {
                $seo->{$columna} = $valor;
            }
            $seo->save();
            $escritos++;

            // Con --fix la tabla interesa que muestre lo que QUEDA pendiente,
            // no el estado de partida que ya acabamos de corregir.
            $filas[] = $this->fila(
                $fqdn,
                trim((string) ($seo->seo_title ?? '')) !== '' ? 'ok' : $estado,
                $seo->seo_title ?: $tituloActual,
                $seo
            );

            $this->line('  <info>' . $fqdn . '</info>');
            foreach ($cambios as $columna => $valor) {
                $this->line('    ' . $columna . ': ' . $valor);
            }
        }

        if ($previo) {
            $env->tenant($previo);
        }

        $this->newLine();
        $this->table(['dominio', 'estado', 'titulo que publica', 'catalogo', 'verificacion'], $filas);
        $this->newLine();

        $sinComercial = count(array_filter($filas, fn ($f) => $f[1] === 'FALTA NOMBRE COMERCIAL'));

        if ($fix) {
            $this->info('Tiendas actualizadas: ' . $escritos);
            $this->line('Queda pegar a mano el token de Search Console en cada tienda con «GSC: NO»');
            $this->line('(Configuracion > SEO). Cada subdominio es una propiedad distinta.');
        } else {
            $this->warn('Tiendas con SEO incompleto: ' . $criticos . ' de ' . count($filas));
            $this->line('Corre el mismo comando con --fix para rellenar titulo y descripcion.');
        }

        if ($sinComercial) {
            $this->newLine();
            $this->warn($sinComercial . ' tienda(s) publican la razon social como titulo y no tienen');
            $this->line('nombre comercial configurado. Eso no lo puede arreglar este comando: hay que');
            $this->line('poner «Nombre comercial» en los datos de la empresa, o un seo_title a mano.');
        }

        return self::SUCCESS;
    }

    /**
     * ¿Esta descripcion la genero este comando, o la escribio una persona?
     *
     * Se reconoce por la plantilla: empieza por «Tienda online de» o
     * «Catalogo de» y acaba en la coletilla fija. Se aceptan las dos grafias de
     * «catalogo» porque las primeras pasadas la escribieron sin tilde.
     *
     * Importa acertar: con --rewrite esto decide que se sobreescribe, y pisar
     * lo que un vendedor escribio a mano seria perder su trabajo.
     */
    private function laEscribimosNosotros(string $desc): bool
    {
        $empieza = str_starts_with($desc, 'Tienda online de ')
                || str_starts_with($desc, 'Catálogo de ')
                || str_starts_with($desc, 'Catalogo de ');

        return $empieza && str_ends_with($desc, 'desde tu celular.');
    }

    /**
     * La meta descripcion de una tienda, dentro del limite y SIN cortar a
     * mitad de palabra.
     *
     * El limite de 155 caracteres no es un capricho: por encima, Google corta
     * la descripcion en los resultados. Pero cortar por bytes deja frases como
     * «Accesorios / repuestos para aparatos para el cuidado de la ropa l...»,
     * que es lo que salio en los dos catalogos importados de Saga, cuyas
     * categorias son enumeraciones larguisimas. Asi que en vez de recortar la
     * frase, se van quitando CATEGORIAS hasta que cabe entera.
     */
    private function describir(string $nombre, string $cats): string
    {
        $cola  = 'Mira el catálogo y compra desde tu celular.';
        $corta = 'Tienda online de ' . $nombre . '. ' . $cola;

        if ($cats === '') {
            return $this->recortar($corta, 155);
        }

        // De mas categorias a menos, la primera que quepa gana.
        $lista = array_map('trim', explode(',', $cats));

        for ($n = count($lista); $n >= 1; $n--) {
            $frase = 'Tienda online de ' . $nombre . ': '
                   . implode(', ', array_slice($lista, 0, $n)) . '. ' . $cola;

            if (mb_strlen($frase) <= 155) {
                return $frase;
            }
        }

        // Ni con una sola categoria cabe: mejor sin ellas que a medias.
        return $this->recortar($corta, 155);
    }

    /** Una fila de la tabla final. */
    private function fila(string $fqdn, string $estado, string $titulo, $seo): array
    {
        return [
            $fqdn,
            $estado,
            $this->recortar($titulo, 38),
            $this->productosPublicados() . ' prod',
            'GSC: ' . (trim((string) ($seo->google_site_verification ?? '')) !== '' ? 'si' : 'NO'),
        ];
    }

    /** Cuantos productos entrarian en el sitemap de esta tienda. */
    private function productosPublicados(): int
    {
        try {
            return Item::where('apply_store', 1)
                ->whereNotNull('internal_id')
                ->whereNotNull('slug')
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Nombres de las categorias que de verdad tienen producto publicado, para
     * que la descripcion de cada tienda sea distinta de la del vecino.
     */
    private function categoriasConProducto(int $limite): string
    {
        try {
            $ids = Item::where('apply_store', 1)
                ->whereNotNull('category_id')
                ->distinct()
                ->pluck('category_id');

            if ($ids->isEmpty()) {
                return '';
            }

            return (string) Category::whereIn('id', $ids)
                ->pluck('name')
                ->filter()
                ->take($limite)
                ->implode(', ');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Recorta por PALABRAS, no por caracteres: esto acaba en un resultado de
     * Google y «...cuidado de la ropa l...» no lo lee nadie.
     */
    private function recortar(string $texto, int $max): string
    {
        if (mb_strlen($texto) <= $max) {
            return $texto;
        }

        $corte = mb_substr($texto, 0, $max - 1);

        // Retroceder a la ultima palabra completa, salvo que eso dejara el
        // texto en nada (una sola palabra larguisima).
        $espacio = mb_strrpos($corte, ' ');
        if ($espacio !== false && $espacio >= (int) ($max * 0.5)) {
            $corte = mb_substr($corte, 0, $espacio);
        }

        return rtrim($corte, " \t,;:.-") . '...';
    }
}
