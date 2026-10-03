<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * El panel «De qué campaña vienen los pedidos» del dashboard del SuperAdmin.
 *
 * Se prueba el partial aislado, no la página completa: el header del layout
 * resuelve el usuario por el guard por defecto, que apunta a la conexión de
 * tenant, y fuera de un request real no existe. Renderizar el partial cubre
 * lo que puede romperse de verdad — que una cifra desaparezca, que el aviso
 * salga cuando no toca, o que una campaña sin pedidos no se vea.
 */
class MarketplaceCampaignReportTest extends TestCase
{
    private const PARTIAL = 'system.marketplace.partials.campaign-report';

    /** Fila tal como la arma el controlador. */
    private function fila(array $over = []): object
    {
        return (object) ($over + [
            'source'   => null,
            'campaign' => null,
            'medium'   => null,
            'orders'   => 0,
            'revenue'  => 0.0,
            'ticket'   => 0.0,
            'leads'    => 0,
        ]);
    }

    private function stats(array $over = []): array
    {
        return $over + [
            'orders_total'       => 0,
            'orders_attributed'  => 0,
            'coverage'           => 0.0,
            'revenue_attributed' => 0.0,
            'campaigns'          => 0,
        ];
    }

    private function render($byCampaign, array $stats): string
    {
        return View::make(self::PARTIAL, [
            'byCampaign'    => collect($byCampaign),
            'campaignStats' => $stats,
        ])->render();
    }

    public function test_muestra_cada_campana_con_sus_cifras()
    {
        $html = $this->render([
            $this->fila([
                'source' => 'tiktok', 'campaign' => 'verano-2026', 'medium' => 'cpc',
                'orders' => 2, 'revenue' => 400.50, 'ticket' => 200.25, 'leads' => 3,
            ]),
        ], $this->stats([
            'orders_total' => 2, 'orders_attributed' => 2, 'coverage' => 100.0,
            'revenue_attributed' => 400.50, 'campaigns' => 1,
        ]));

        $this->assertStringContainsString('tiktok', $html);
        $this->assertStringContainsString('verano-2026', $html);
        $this->assertStringContainsString('200.25', $html);
        $this->assertStringContainsString('400.50', $html);
    }

    /**
     * La cobertura es el primer número a mirar: con pedidos sin origen, el
     * resto de la tabla no dice nada todavía y hay que explicar por qué.
     */
    public function test_con_cobertura_cero_explica_que_faltan_los_utm()
    {
        $html = $this->render(
            [$this->fila(['orders' => 7, 'revenue' => 700.0, 'ticket' => 100.0])],
            $this->stats(['orders_total' => 7])
        );

        $this->assertStringContainsString('utm_source=tiktok', $html);
        $this->assertStringContainsString('Sin campaña', $html);
    }

    /** Con cobertura, el aviso estorba y no debe salir. */
    public function test_con_cobertura_no_sale_el_aviso()
    {
        $html = $this->render(
            [$this->fila(['source' => 'meta', 'campaign' => 'navidad', 'orders' => 1, 'revenue' => 99.9, 'ticket' => 99.9])],
            $this->stats([
                'orders_total' => 1, 'orders_attributed' => 1, 'coverage' => 100.0,
                'revenue_attributed' => 99.9, 'campaigns' => 1,
            ])
        );

        $this->assertStringNotContainsString('utm_source=tiktok', $html);
    }

    /**
     * Una campaña que genera interés pero no vende es justo la que hay que
     * arreglar: no puede desaparecer del informe por no tener pedidos.
     */
    public function test_una_campana_solo_con_leads_sigue_apareciendo()
    {
        $html = $this->render(
            [$this->fila(['source' => 'meta', 'campaign' => 'test-leads', 'leads' => 12])],
            $this->stats(['campaigns' => 1])
        );

        $this->assertStringContainsString('test-leads', $html);
        $this->assertStringContainsString('12', $html);
    }

    /** Sin pedidos ni leads, lo honesto es decirlo, no pintar una tabla vacía. */
    public function test_rango_sin_actividad_lo_dice()
    {
        $html = $this->render([], $this->stats());

        $this->assertStringContainsString('Sin pedidos ni leads', $html);
    }

    /**
     * La barra de cobertura nunca llega a cero ancho: una barra invisible
     * parece un fallo de render en vez de un dato.
     */
    public function test_la_barra_de_cobertura_siempre_se_ve()
    {
        $html = $this->render([], $this->stats());

        $this->assertStringContainsString('width:1%', $html);
    }

    /**
     * El informe cuenta pedidos del marketplace y el KPI de arriba cuenta
     * sub-pedidos por tienda. Los dos números no coinciden, y la nota que lo
     * explica es lo que evita que se reporte como bug.
     */
    public function test_advierte_que_no_cuadra_con_el_kpi_de_pedidos()
    {
        $html = $this->render([], $this->stats());

        $this->assertStringContainsString('sub-pedidos por tienda', $html);
    }
}
