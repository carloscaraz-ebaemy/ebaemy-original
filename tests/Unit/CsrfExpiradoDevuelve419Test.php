<?php

namespace Tests\Unit;

use App\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

/**
 * Un token CSRF caducado tiene que responder 419, tambien cuando quien
 * pregunta espera JSON.
 *
 * No es cosmetico. 419 significa «tu token ya no vale, pide otro y reintenta»;
 * 500 significa «el servidor se rompio». Son dos cosas distintas para quien
 * llama: con un 500 no hay forma de recuperarse, y encima mete un fallo que en
 * realidad es normal en el recuento de incidentes.
 *
 * Le pasaba a TODAS las llamadas por fetch del marketplace (favoritos,
 * carrito, cupones): `TokenMismatchException` no es una `HttpException`, asi
 * que no entraba por la rama de los codigos HTTP y se caia hasta el
 * `errorResponse('', 500, ...)` del final de Handler::render. Con HTML si
 * respondia 419, y eso es lo que lo mantuvo escondido.
 *
 * Se prueba el Handler directamente, no por HTTP: en pruebas el middleware de
 * CSRF se desactiva solo (`VerifyCsrfToken::runningUnitTests`), asi que por
 * ahi no hay forma de provocar el fallo.
 */
class CsrfExpiradoDevuelve419Test extends TestCase
{
    private function render(Request $request)
    {
        return app(Handler::class)->render($request, new TokenMismatchException('CSRF token mismatch.'));
    }

    private function peticion(array $headers = []): Request
    {
        $r = Request::create('/marketplace/favorites/toggle', 'POST', ['listing_id' => 1]);
        foreach ($headers as $k => $v) {
            $r->headers->set($k, $v);
        }

        return $r;
    }

    public function test_pidiendo_json_responde_419_y_no_500(): void
    {
        $res = $this->render($this->peticion(['Accept' => 'application/json']));

        $this->assertSame(419, $res->getStatusCode(), 'un token caducado no es un error 500');
        $this->assertSame(
            false,
            json_decode($res->getContent(), true)['success'] ?? null,
            'el cuerpo tiene que seguir diciendo success:false'
        );
    }

    public function test_una_llamada_de_fetch_normal_tambien(): void
    {
        // Tal como la manda mpCsrfHeaders(): JSON + la cabecera del token.
        $res = $this->render($this->peticion([
            'Accept'         => 'application/json',
            'Content-Type'   => 'application/json',
            'X-XSRF-TOKEN'   => 'ya-no-vale',
        ]));

        $this->assertSame(419, $res->getStatusCode());
    }

    public function test_navegando_con_html_sigue_dando_419(): void
    {
        // El camino que ya funcionaba antes del arreglo: que no se rompa.
        $res = $this->render($this->peticion(['Accept' => 'text/html']));

        $this->assertSame(419, $res->getStatusCode());
    }
}
