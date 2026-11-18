<?php

namespace Tests\Unit\Marketplace;

use App\Models\System\MarketplaceListing;
use App\Services\Marketplace\SearchInsights;
use App\Services\System\MarketplaceListingSyncService;
use Tests\TestCase;

/**
 * Búsqueda flexible, medidas en la consulta, índice con descripción y ruido en
 * las estadísticas (2026-11-18). Datos de producción del último mes: 87 de 342
 * búsquedas sin resultados, varias con el producto publicado («arbol pino
 * verde», «floreros colgantes») y 29 de un robot con `{search_term_string}`.
 *
 * Lo que necesita base de datos (que la consulta traiga los pinos, que «polo
 * rojo» siga eligiendo la variante Rojo) se verificó con datos de prueba en
 * una transacción con rollback; aquí va la lógica pura.
 */
class MarketplaceLooseSearchTest extends TestCase
{
    // ── Umbral de la búsqueda flexible ────────────────────────────────────

    public function test_exige_la_mitad_de_las_palabras_redondeando_hacia_arriba()
    {
        $this->assertSame(1, MarketplaceListing::looseMinMatches('floreros colgantes'));
        $this->assertSame(2, MarketplaceListing::looseMinMatches('arbol pino verde'));
        $this->assertSame(2, MarketplaceListing::looseMinMatches('arbol pino verde grande'));
        $this->assertSame(3, MarketplaceListing::looseMinMatches('arbol pino verde grande luces'));
    }

    public function test_las_palabras_de_relleno_no_cuentan()
    {
        // «de» no es contenido: son 3 palabras, hacen falta 2.
        $this->assertSame(['arbol', 'navidad', '2.40'], MarketplaceListing::looseTokens('arbol de navidad 2.40'));
        $this->assertSame(2, MarketplaceListing::looseMinMatches('arbol de navidad 2.40'));
    }

    public function test_sin_busqueda_flexible_con_una_sola_palabra_o_solo_relleno()
    {
        // Con una palabra «la mitad» es esa palabra: la exacta ya dijo que no.
        $this->assertNull(MarketplaceListing::looseMinMatches('florero'));
        $this->assertNull(MarketplaceListing::looseMinMatches('para la'));
        $this->assertNull(MarketplaceListing::looseMinMatches(''));
        $this->assertNull(MarketplaceListing::looseMinMatches(null));
    }

    public function test_la_busqueda_flexible_tiene_un_tope_de_palabras()
    {
        $tokens = MarketplaceListing::looseTokens('uno dos tres cuatro cinco seis siete ocho nueve diez');
        $this->assertCount(8, $tokens);
        $this->assertSame(4, MarketplaceListing::looseMinMatches('uno dos tres cuatro cinco seis siete ocho nueve diez'));
    }

    // ── Medidas en la consulta ────────────────────────────────────────────

    public function test_la_consulta_junta_numero_y_unidad_en_un_token()
    {
        $this->assertSame(['pino', '240cm'], MarketplaceListing::searchTokens('pino 2,40 m'));
        $this->assertSame(['arbol', '200cm'], MarketplaceListing::searchTokens('arbol 2metros'));
        $this->assertSame(['arbol', '200cm'], MarketplaceListing::searchTokens('arbol 2 m'));
        $this->assertSame(['240cm'], MarketplaceListing::searchTokens('240 cm'));
        // La coma sigue separando palabras cuando no es decimal.
        $this->assertSame(['polo', 'rojo'], MarketplaceListing::searchTokens('polo,rojo'));
    }

    public function test_un_decimal_suelto_busca_tambien_los_centimetros()
    {
        $this->assertContains('240cm', MarketplaceListing::tokenSpellings('2.40'));
        $this->assertContains('2.40', MarketplaceListing::tokenSpellings('2.40'));
        $this->assertContains('240cm', MarketplaceListing::tokenExpansions('2.40'));
    }

    public function test_la_relevancia_reconoce_la_medida_canonica()
    {
        $sql = MarketplaceListing::textRelevanceSql('arbol 2.40');
        $this->assertStringContainsString("240cm", $sql);
    }

    // ── Índice con descripción ────────────────────────────────────────────

    public function test_el_indice_lleva_palabras_utiles_de_la_descripcion()
    {
        $txt = MarketplaceListingSyncService::buildSearchText(
            ['🎄 Pino Americano Artificial 2.1 m', 'Navidad 2025', 'Arboles de Navidad Navidad'],
            '<p>Pino <strong>frondoso</strong> color verde intenso.</p><p>Incluye&nbsp;base.</p>'
        );

        $this->assertStringStartsWith('🎄 pino americano artificial 210cm navidad 2025 arboles', $txt);
        $this->assertStringContainsString(' verde', $txt);
        $this->assertStringContainsString(' frondoso', $txt);
        $this->assertStringContainsString(' incluye base', $txt);
        // Sin HTML, sin relleno, sin repetir lo que ya está.
        $this->assertStringNotContainsString('<', $txt);
        $this->assertStringNotContainsString('strong', $txt);
        $this->assertSame(1, substr_count($txt, 'pino'));
    }

    public function test_lo_decisivo_va_primero_y_la_descripcion_no_pasa_del_limite()
    {
        $max = MarketplaceListingSyncService::searchTextMax();
        $titulo = 'Polo basico algodon';
        $txt = MarketplaceListingSyncService::buildSearchText(
            [$titulo, 'Polos', 'Rojo Azul'],
            str_repeat('palabra' . ' ', 10) . implode(' ', array_map(fn ($i) => 'termino' . $i, range(1, 400)))
        );

        $this->assertStringStartsWith('polo basico algodon polos rojo azul', $txt);
        $this->assertLessThanOrEqual($max, mb_strlen($txt));
        // Corta en palabra entera, no a mitad.
        $this->assertMatchesRegularExpression('/ termino\d+$/', $txt);
    }

    public function test_sin_descripcion_el_indice_es_lo_de_siempre()
    {
        $this->assertSame(
            'polo basico rojo',
            MarketplaceListingSyncService::buildSearchText(['Polo Básico', '', 'Rojo'], null)
        );
    }

    // ── Ruido en las estadísticas ─────────────────────────────────────────

    public function test_el_marcador_de_schema_org_es_ruido()
    {
        $this->assertTrue(SearchInsights::isNoise('{search_term_string}'));
        $this->assertTrue(SearchInsights::isNoise('polo {x}'));
        $this->assertTrue(SearchInsights::isNoise('}'));
        $this->assertFalse(SearchInsights::isNoise('arbol pino verde'));
        $this->assertFalse(SearchInsights::isNoise('polo (rojo)'));
    }

    public function test_el_ruido_no_se_registra()
    {
        // Si llegara a la base de datos, fallaría o escribiría: con el
        // marcador tiene que volver antes sin tocarla.
        \DB::shouldReceive('connection')->never();
        (new SearchInsights())->record('{search_term_string}', 0);
        $this->assertTrue(true);
    }
}
