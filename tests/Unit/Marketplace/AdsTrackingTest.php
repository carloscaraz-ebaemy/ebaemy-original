<?php

namespace Tests\Unit\Marketplace;

use App\Http\Middleware\CaptureMarketplaceAttribution;
use App\Services\Marketplace\AdsTracking;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\TestCase;

/**
 * Lo que se mide en publicidad falla en silencio: nada da error, simplemente
 * los informes no cuadran semanas después. Estos tests cubren los tres puntos
 * donde eso pasa.
 */
class AdsTrackingTest extends TestCase
{
    // ── content_id ────────────────────────────────────────────────────────

    /**
     * El `content_id` tiene que ser `mp_{listing_id}` — exactamente el `<g:id>`
     * que emite el feed. Si divergen, Meta y TikTok reportan 0 % de
     * coincidencia de catálogo y los anuncios dinámicos no arrancan nunca.
     */
    public function test_content_id_usa_el_mismo_prefijo_que_el_feed()
    {
        $this->assertSame('mp_123', AdsTracking::contentId(123));
        $this->assertSame('mp_123', AdsTracking::contentId('123'));
        $this->assertSame('mp_0', AdsTracking::contentId(null));
    }

    // ── Deduplicación ─────────────────────────────────────────────────────

    /**
     * El Purchase del navegador y el del servidor tienen que llevar el MISMO
     * event_id para que la plataforma deduplique. Se siembra con el número de
     * pedido, así que la coincidencia no depende de que ambos se emitan en la
     * misma petición ni el mismo día.
     */
    public function test_event_id_sembrado_es_estable_entre_navegador_y_servidor()
    {
        $delNavegador = AdsTracking::eventId('purchase', 'MP-00042');
        $delServidor  = AdsTracking::eventId('purchase', 'MP-00042');

        $this->assertSame($delNavegador, $delServidor);
        $this->assertNotSame($delNavegador, AdsTracking::eventId('purchase', 'MP-00043'));
    }

    /** Sin semilla no puede repetirse: cada emisión es un evento distinto. */
    public function test_event_id_sin_semilla_es_distinto_cada_vez()
    {
        $this->assertNotSame(
            AdsTracking::eventId('view_content'),
            AdsTracking::eventId('view_content')
        );
    }

    /**
     * Un evento que sólo vive en el navegador NO lleva event_id: el payload se
     * renderiza una vez, así que un segundo AddToCart legítimo llegaría con el
     * id del primero y la plataforma lo descartaría como duplicado.
     */
    public function test_payload_solo_lleva_event_id_cuando_hay_semilla()
    {
        $sinSemilla = AdsTracking::payload('add_to_cart', [], 10.0);
        $conSemilla = AdsTracking::payload('purchase', [], 10.0, 'MP-1');

        $this->assertArrayNotHasKey('event_id', $sinSemilla);
        $this->assertArrayHasKey('event_id', $conSemilla);
    }

    public function test_payload_redondea_el_valor_y_fija_la_moneda()
    {
        $payload = AdsTracking::payload('purchase', [], 19.999, 'MP-1');

        $this->assertSame(20.0, $payload['value']);
        $this->assertSame('PEN', $payload['currency']);
    }

    public function test_item_del_carrito_sale_con_el_content_id_del_feed()
    {
        $item = AdsTracking::itemFromCartLine([
            'listing_id' => 77,
            'title'      => 'Polo azul',
            'quantity'   => 3,
            'price'      => 49.9,
        ]);

        $this->assertSame('mp_77', $item['content_id']);
        $this->assertSame('Polo azul', $item['content_name']);
        $this->assertSame(3, $item['quantity']);
        $this->assertSame(49.9, $item['price']);
    }

    /** Una cantidad de 0 o negativa rompería el valor del evento. */
    public function test_item_del_carrito_nunca_baja_de_una_unidad()
    {
        $item = AdsTracking::itemFromCartLine(['listing_id' => 1, 'quantity' => 0]);

        $this->assertSame(1, $item['quantity']);
    }

    // ── Atribución ────────────────────────────────────────────────────────

    public function test_el_middleware_guarda_el_origen_de_la_primera_visita()
    {
        $request = $this->requestConSesion('/marketplace?utm_source=tiktok&utm_campaign=verano&ttclid=ABC123');

        $this->middleware()->handle($request, fn () => 'ok');

        $atribucion = $request->session()->get(AdsTracking::ATTRIBUTION_KEY);

        $this->assertSame('tiktok', $atribucion['utm_source']);
        $this->assertSame('verano', $atribucion['utm_campaign']);
        $this->assertSame('ABC123', $atribucion['ttclid']);
    }

    /**
     * Si alguien llega por un anuncio, navega, se va y vuelve escribiendo la
     * URL a mano, la venta sigue siendo del anuncio. Sobrescribir aquí sería
     * regalarle la conversión al tráfico directo.
     */
    public function test_una_visita_sin_parametros_no_borra_el_origen_anterior()
    {
        $primera = $this->requestConSesion('/marketplace?utm_source=tiktok&utm_campaign=verano');
        $this->middleware()->handle($primera, fn () => 'ok');

        $segunda = $this->requestConSesion('/marketplace/item/polo-azul');
        $this->middleware()->handle($segunda, fn () => 'ok');

        $this->assertSame(
            'tiktok',
            $segunda->session()->get(AdsTracking::ATTRIBUTION_KEY)['utm_source']
        );
    }

    /** Un clic de otra campaña sí reemplaza: es tráfico nuevo y pagado. */
    public function test_un_clic_de_anuncio_nuevo_reemplaza_el_origen()
    {
        $primera = $this->requestConSesion('/marketplace?utm_source=tiktok');
        $this->middleware()->handle($primera, fn () => 'ok');

        $segunda = $this->requestConSesion('/marketplace?utm_source=meta&fbclid=XYZ');
        $this->middleware()->handle($segunda, fn () => 'ok');

        $atribucion = $segunda->session()->get(AdsTracking::ATTRIBUTION_KEY);

        $this->assertSame('meta', $atribucion['utm_source']);
        $this->assertSame('XYZ', $atribucion['fbclid']);
        // El utm_source anterior no puede quedar pegado junto al nuevo.
        $this->assertArrayNotHasKey('ttclid', $atribucion);
    }

    /** Un POST no es el aterrizaje de un anuncio. */
    public function test_el_middleware_ignora_peticiones_que_no_son_get()
    {
        $request = Request::create('/marketplace/cart', 'POST', ['utm_source' => 'tiktok']);
        $request->setLaravelSession($this->sesion);

        $this->middleware()->handle($request, fn () => 'ok');

        $this->assertSame([], AdsTracking::attribution() ?: []);
        $this->assertNull($request->session()->get(AdsTracking::ATTRIBUTION_KEY));
    }

    // ── Columnas y formato que exigen las plataformas ─────────────────────

    public function test_las_columnas_de_atribucion_omiten_lo_que_esta_vacio()
    {
        $request = $this->requestConSesion('/marketplace?utm_source=tiktok&utm_campaign=verano');
        $this->middleware()->handle($request, fn () => 'ok');

        $cols = AdsTracking::attributionColumns();

        $this->assertSame('tiktok', $cols['utm_source']);
        $this->assertSame('verano', $cols['utm_campaign']);
        $this->assertArrayNotHasKey('utm_medium', $cols);
        $this->assertArrayNotHasKey('click_id_fb', $cols);
    }

    /**
     * Meta no acepta el `fbclid` pelado: exige `fb.1.{ms}.{fbclid}` y descarta
     * el evento sin avisar si llega de otra forma.
     */
    public function test_el_fbc_de_meta_lleva_el_formato_que_meta_exige()
    {
        $request = $this->requestConSesion('/marketplace?fbclid=PRUEBA123');
        $this->middleware()->handle($request, fn () => 'ok');

        $fbc = AdsTracking::metaFbc();

        $this->assertMatchesRegularExpression('/^fb\.1\.\d{13}\.PRUEBA123$/', $fbc);
    }

    public function test_sin_fbclid_no_hay_fbc_que_mandar()
    {
        $request = $this->requestConSesion('/marketplace');
        $this->middleware()->handle($request, fn () => 'ok');

        $this->assertNull(AdsTracking::metaFbc());
    }

    // ── Identificadores desde el pedido (webhook de la pasarela) ──────────

    /**
     * En el webhook no hay comprador delante: la peticion la hace la pasarela
     * desde sus servidores. Los identificadores tienen que salir del pedido o
     * la venta no se puede casar con el anuncio.
     */
    public function test_el_fbc_del_pedido_lleva_el_formato_de_meta()
    {
        $pedido = (object) [
            'click_id_fb' => 'PEDIDO123',
            'click_id_tt' => null,
            'created_at'  => \Carbon\Carbon::parse('2026-10-03 12:00:00'),
        ];

        $datos = AdsTracking::userDataFromOrder($pedido);

        $this->assertMatchesRegularExpression('/^fb\.1\.\d{13}\.PEDIDO123$/', $datos['fbc']);
    }

    public function test_el_ttclid_del_pedido_viaja_tal_cual()
    {
        $pedido = (object) [
            'click_id_fb' => null,
            'click_id_tt' => 'TT987',
            'created_at'  => null,
        ];

        $this->assertSame('TT987', AdsTracking::userDataFromOrder($pedido)['ttclid']);
    }

    /**
     * Un pedido sin origen no manda identificadores vacios: la IP y el user
     * agent de la pasarela no dicen nada de quien compro, y mandarlos seria
     * peor que no mandar nada.
     */
    public function test_un_pedido_sin_origen_no_manda_identificadores()
    {
        $pedido = (object) ['click_id_fb' => null, 'click_id_tt' => null, 'created_at' => null];

        $this->assertSame([], AdsTracking::userDataFromOrder($pedido));
    }

    // ── Utilidades ────────────────────────────────────────────────────────

    private Store $sesion;

    /**
     * AdsTracking lee la sesión por la fachada. Aquí no hay app de Laravel
     * levantada, así que enlazamos una sesión de array al contenedor: es lo
     * que permite probar la lógica sin arrancar el framework entero.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->sesion = new Store('mp_test', new ArraySessionHandler(120));

        $container = new \Illuminate\Container\Container();
        $container->instance('session', $this->sesion);
        \Illuminate\Support\Facades\Facade::setFacadeApplication($container);
    }

    private function middleware(): CaptureMarketplaceAttribution
    {
        return new CaptureMarketplaceAttribution();
    }

    private function requestConSesion(string $uri): Request
    {
        $request = Request::create($uri, 'GET');
        $request->setLaravelSession($this->sesion);

        return $request;
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\Facade::clearResolvedInstances();
        \Illuminate\Support\Facades\Facade::setFacadeApplication(null);

        parent::tearDown();
    }
}
