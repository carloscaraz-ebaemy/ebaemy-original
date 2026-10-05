<?php

namespace Tests\Unit;

use App\Services\System\SpanishPhonetics;
use PHPUnit\Framework\TestCase;

/**
 * Reportado el 2026-10-05: quien busca «cogines» no encuentra nada aunque el
 * catálogo esté lleno de «cojines», y el buscador no ofrece ninguna
 * alternativa. Se pronuncian igual y se escriben distinto.
 *
 * Estos tests fijan las dos mitades del contrato: qué palabras se consideran
 * la misma (clave canónica) y que al teclear una grafía se generen las otras.
 * Y, sobre todo, lo que NO debe pasar: unir dos palabras distintas.
 */
class SpanishPhoneticsTest extends TestCase
{
    // ── Clave canónica ────────────────────────────────────────────────────

    /**
     * @dataProvider suenanIgual
     */
    public function test_dos_grafias_que_suenan_igual_dan_la_misma_clave(string $a, string $b): void
    {
        $this->assertSame(
            SpanishPhonetics::key($a),
            SpanishPhonetics::key($b),
            "«{$a}» y «{$b}» se leen igual y deberían compartir clave"
        );
    }

    public static function suenanIgual(): array
    {
        return [
            'g/j ante i'        => ['cojines', 'cogines'],
            'g/j ante e'        => ['girasol', 'jirasol'],
            'b/v'               => ['vaso', 'baso'],
            'b/v interno'       => ['ventilador', 'bentilador'],
            'z/s'               => ['zapato', 'sapato'],
            'c/s ante i'        => ['cocina', 'cosina'],
            'z/s interno'       => ['cabeza', 'cabesa'],
            'll/y (yeismo)'     => ['llave', 'yave'],
            'c/k'               => ['casa', 'kasa'],
            'qu/k'              => ['queso', 'keso'],
            'h muda inicial'    => ['hora', 'ora'],
            'h muda interna'    => ['almohada', 'almoada'],
            'rr/r'              => ['carro', 'caro'],
            'nasal mb/nv'       => ['invierno', 'imbierno'],
            'seseo casa/caza'   => ['casa', 'caza'],
            'tilde da igual'    => ['Cojín', 'cojin'],
            'mayusculas'        => ['COJINES', 'cojines'],
        ];
    }

    /**
     * El riesgo de esto no es quedarse corto, es pasarse: si la clave uniera
     * palabras que no se parecen, el buscador devolvería cualquier cosa y
     * sería peor que no tolerar nada.
     *
     * @dataProvider suenanDistinto
     */
    public function test_palabras_distintas_no_comparten_clave(string $a, string $b): void
    {
        $this->assertNotSame(
            SpanishPhonetics::key($a),
            SpanishPhonetics::key($b),
            "«{$a}» y «{$b}» son palabras distintas y no deberían compartir clave"
        );
    }

    public static function suenanDistinto(): array
    {
        return [
            'g ante a no es j' => ['gato', 'jato'],
            'vocal distinta'   => ['polo', 'pelo'],
            'vocal distinta 2' => ['mesa', 'masa'],
            'consonante'       => ['pan', 'pin'],
            'cojin vs cajon'   => ['cojin', 'cajon'],
            'mas letras'       => ['silla', 'sillon'],
            'nada que ver'     => ['zapato', 'cojines'],
        ];
    }

    public function test_la_cadena_vacia_da_clave_vacia(): void
    {
        $this->assertSame('', SpanishPhonetics::key(''));
        $this->assertSame('', SpanishPhonetics::key('  '));
        $this->assertSame('', SpanishPhonetics::key('!!!'));
    }

    // ── Variantes de escritura ────────────────────────────────────────────

    /**
     * El caso de uso real: se teclea mal y hay que generar la grafía buena,
     * porque es la que está guardada en el catálogo.
     *
     * @dataProvider erratas
     */
    public function test_al_teclear_mal_se_genera_la_grafia_correcta(string $tecleado, string $catalogo): void
    {
        $variantes = SpanishPhonetics::spellingVariants($tecleado, 8);

        $this->assertContains(
            SpanishPhonetics::normalize($catalogo),
            $variantes,
            "buscando «{$tecleado}» había que llegar a «{$catalogo}»; salió: " . implode(', ', $variantes)
        );
    }

    public static function erratas(): array
    {
        return [
            ['cogines', 'cojines'],
            ['baso', 'vaso'],
            ['sapato', 'zapato'],
            ['yave', 'llave'],
            ['almoada', 'almohada'],
            ['cosina', 'cocina'],
            ['kasa', 'casa'],
            ['keso', 'queso'],
            ['ora', 'hora'],
            ['caro', 'carro'],
            ['jirasol', 'girasol'],
            ['cabesa', 'cabeza'],
            ['sorro', 'zorro'],
            ['bentilador', 'ventilador'],
            ['imbierno', 'invierno'],
            ['aciento', 'asiento'],
        ];
    }

    /**
     * Y al revés: quien escribe bien tiene que encontrar el producto que el
     * vendedor dio de alta con la falta.
     */
    public function test_tambien_funciona_del_derecho(): void
    {
        $this->assertContains('cogines', SpanishPhonetics::spellingVariants('cojines', 8));
        $this->assertContains('baso', SpanishPhonetics::spellingVariants('vaso', 8));
        $this->assertContains('sapato', SpanishPhonetics::spellingVariants('zapato', 8));
    }

    /** La palabra tecleada va siempre primera: nunca se sustituye, solo se amplía. */
    public function test_la_palabra_tecleada_va_primera(): void
    {
        $this->assertSame('cogines', SpanishPhonetics::spellingVariants('cogines')[0]);
        $this->assertSame('vaso', SpanishPhonetics::spellingVariants('VASO')[0]);
        $this->assertSame('cojin', SpanishPhonetics::spellingVariants('Cojín')[0]);
    }

    /**
     * Cada variante son cuatro `LIKE` más en el SQL de relevancia, y se usan
     * hasta 5 tokens por búsqueda. El tope no es cosmético.
     */
    public function test_respeta_el_tope_de_variantes(): void
    {
        foreach (['cabesa', 'vaselina', 'jirasol', 'cosina'] as $palabra) {
            $this->assertLessThanOrEqual(3, count(SpanishPhonetics::spellingVariants($palabra, 3)));
        }
    }

    /**
     * Toda variante tiene que sonar igual que la original. Es la red que evita
     * que una sustitución aplicada donde no tocaba cuele otra palabra.
     */
    public function test_toda_variante_suena_igual_que_la_original(): void
    {
        foreach (['cogines', 'vaselina', 'almoada', 'queso', 'carro', 'invierno', 'zapatillas'] as $palabra) {
            $clave = SpanishPhonetics::key($palabra);

            foreach (SpanishPhonetics::spellingVariants($palabra, 8) as $variante) {
                $this->assertSame(
                    $clave,
                    SpanishPhonetics::key($variante),
                    "«{$variante}» salió como variante de «{$palabra}» pero no suena igual"
                );
            }
        }
    }

    /**
     * Con una o dos letras cualquier sustitución es ruido: «de» daría «te», y
     * un token así no filtra nada útil.
     */
    public function test_las_palabras_muy_cortas_no_se_tocan(): void
    {
        $this->assertSame(['tv'], SpanishPhonetics::spellingVariants('tv'));
        $this->assertSame(['a'], SpanishPhonetics::spellingVariants('a'));
        $this->assertSame([], SpanishPhonetics::spellingVariants('!!'));
    }

    /**
     * El resultado se interpola en un `LIKE` dentro de `textRelevanceSql`, así
     * que no puede salir de aquí nada que no sea letra o dígito.
     */
    public function test_las_variantes_son_seguras_para_interpolar_en_sql(): void
    {
        foreach (["cojin'; DROP TABLE items; --", 'cojin%_\\', 'co"jin', 'cojín <b>'] as $sucio) {
            foreach (SpanishPhonetics::spellingVariants($sucio, 8) as $variante) {
                $this->assertMatchesRegularExpression(
                    '/^[a-z0-9]*$/',
                    $variante,
                    "«{$variante}» no es seguro de interpolar"
                );
            }
        }
    }

    /**
     * normalize() tiene que seguir coincidiendo con
     * MarketplaceListingSyncService::normalizeForSearch en tildes y eñe, porque
     * `search_text` se indexa con aquella y aquí se busca contra esa columna.
     */
    public function test_normalize_quita_tildes_y_la_ene(): void
    {
        $this->assertSame('cojin', SpanishPhonetics::normalize('Cojín'));
        $this->assertSame('muneca', SpanishPhonetics::normalize('Muñeca'));
        $this->assertSame('ergonomica', SpanishPhonetics::normalize('Ergonómica'));
        $this->assertSame('anoantiguo', SpanishPhonetics::normalize('año antiguo'));
    }
}
