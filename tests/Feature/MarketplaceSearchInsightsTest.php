<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * El panel «Qué buscan tus clientes» del dashboard del SuperAdmin.
 *
 * Lo que este panel tiene que hacer bien no es mostrar una tabla: es poner
 * delante lo que NO se encuentra, que es lo accionable, y no confundirlo con
 * el top de búsquedas. Eso es lo que se prueba aquí.
 *
 * Se prueba el partial aislado, como el de campañas: el dashboard completo no
 * se puede renderizar fuera de un request real porque el header del layout
 * resuelve el usuario por el guard por defecto, que apunta a la conexión de
 * tenant.
 */
class MarketplaceSearchInsightsTest extends TestCase
{
    private const PARTIAL = 'system.marketplace.partials.search-insights';

    /** Fila tal como la devuelve SearchInsights. */
    private function termino(string $norm, int $searches, int $zero, int $last): object
    {
        return (object) [
            'term_norm'    => $norm,
            'sample'       => $norm,
            'searches'     => $searches,
            'zero'         => $zero,
            'last_results' => $last,
        ];
    }

    private function render(array $stats, array $top = [], array $zero = []): string
    {
        return View::make(self::PARTIAL, [
            'searchStats'  => $stats + [
                'searches'  => 0,
                'zero'      => 0,
                'terms'     => 0,
                'zero_rate' => 0.0,
            ],
            'topSearches'  => collect($top),
            'zeroSearches' => collect($zero),
        ])->render();
    }

    public function test_muestra_lo_buscado_sin_resultados_con_su_recuento()
    {
        $html = $this->render(
            ['searches' => 10, 'zero' => 4, 'terms' => 3, 'zero_rate' => 40.0],
            [$this->termino('zapatillas', 6, 0, 12)],
            [$this->termino('cocina a gas', 4, 4, 0)]
        );

        $this->assertStringContainsString('cocina a gas', $html);
        $this->assertStringContainsString('4 veces', $html);
        $this->assertStringContainsString('40%', $html);
    }

    /**
     * Sin búsquedas no hay nada que interpretar, y hay que decir por qué:
     * si acaban de desplegarlo, la tabla vacía no significa que nadie busque.
     */
    public function test_sin_busquedas_explica_que_aun_no_hay_datos()
    {
        $html = $this->render([]);

        $this->assertStringContainsString('Todavía no hay búsquedas registradas', $html);
        // Y no pinta las listas, que no tendrían nada que decir.
        $this->assertStringNotContainsString('Buscado sin encontrar nada', $html);
    }

    /** Que nada falle es una buena noticia, y se dice como tal. */
    public function test_sin_terminos_vacios_lo_dice_en_positivo()
    {
        $html = $this->render(
            ['searches' => 8, 'zero' => 0, 'terms' => 2, 'zero_rate' => 0.0],
            [$this->termino('polo', 8, 0, 5)],
            []
        );

        $this->assertStringContainsString('lo que piden, lo tienes', $html);
    }

    /**
     * El consejo sólo tiene sentido cuando hay términos vacíos que mirar;
     * si no, es ruido en pantalla.
     */
    public function test_el_consejo_solo_sale_cuando_hay_algo_que_arreglar()
    {
        $conVacios = $this->render(
            ['searches' => 4, 'zero' => 4, 'terms' => 1, 'zero_rate' => 100.0],
            [],
            [$this->termino('cocina a gas', 4, 4, 0)]
        );
        $sinVacios = $this->render(
            ['searches' => 4, 'zero' => 0, 'terms' => 1, 'zero_rate' => 0.0],
            [$this->termino('polo', 4, 0, 9)],
            []
        );

        $this->assertStringContainsString('salir a captar tiendas', $conVacios);
        $this->assertStringNotContainsString('salir a captar tiendas', $sinVacios);
    }

    /**
     * Un término muy buscado que hoy no devuelve nada tiene que destacarse
     * también en el top: es el caso más urgente de los dos listados.
     */
    public function test_en_el_top_se_marca_el_que_no_devuelve_nada()
    {
        $html = $this->render(
            ['searches' => 9, 'zero' => 9, 'terms' => 1, 'zero_rate' => 100.0],
            [$this->termino('cocina a gas', 9, 9, 0)],
            [$this->termino('cocina a gas', 9, 9, 0)]
        );

        $this->assertStringContainsString('sin resultados', $html);
    }

    /** Lo que se guarda y lo que no, a la vista de quien mira el panel. */
    public function test_dice_que_no_guarda_datos_personales()
    {
        $html = $this->render([]);

        $this->assertStringContainsString('ni IP, ni usuario, ni sesión', $html);
    }
}
