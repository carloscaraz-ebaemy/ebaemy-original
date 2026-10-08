<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * `route:cache` tiene que FALLAR en este proyecto.
 *
 * `routes/web.php` registra un mapa de rutas distinto segun el host que
 * pregunta (las del tenant, o las del sistema y el marketplace). Una cache
 * congela una de las dos ramas y, encima, deja `Route::domain(null)` porque
 * con la configuracion cacheada `env()` ya no lee el .env.
 *
 * El dano concreto, visto en produccion el 2026-10-07: cada
 * `<tenant>.ebaemy.com/marketplace` empezo a servir el marketplace central con
 * su canonical apuntandose a si mismo — 17 copias del marketplace entero
 * compitiendo en Google.
 *
 * Que no se podia hacer ya estaba escrito en la skill de deploy y en
 * `scripts/deploy-orders-flow.sh`, y aun asi se ejecuto tres despliegues
 * seguidos. Por eso ahora hay un comando que lo bloquea, y por eso hay esta
 * prueba: si alguien lo borra para «arreglar el deploy», que se entere aqui y
 * no en produccion.
 */
class RouteCacheProhibidaTest extends TestCase
{
    public function test_route_cache_falla_y_no_escribe_la_cache(): void
    {
        $this->artisan('route:cache')
            ->assertFailed()
            ->expectsOutputToContain('PROHIBIDO');

        $this->assertFileDoesNotExist(
            $this->app->getCachedRoutesPath(),
            'route:cache no debe dejar escrito el fichero de rutas'
        );
    }

    public function test_los_comandos_de_cache_que_si_valen_siguen_estando(): void
    {
        // El deploy depende de estos; si un dia tambien se bloquearan, el
        // despliegue se quedaria sin forma de refrescar nada.
        //
        // Se comprueba que EXISTEN, no se ejecutan: `config:cache` escribe
        // bootstrap/cache/config.php de verdad, y con la config cacheada las
        // pruebas que vienen detras pierden la conexion a la BD de pruebas y
        // se saltan en silencio. Me paso al escribir esta prueba: 12 pruebas
        // pasaron a "skipped" sin que nada lo explicara.
        $comandos = array_keys(\Illuminate\Support\Facades\Artisan::all());

        foreach (['config:cache', 'view:cache', 'route:clear', 'optimize:clear'] as $c) {
            $this->assertContains($c, $comandos, "falta el comando {$c}");
        }
    }
}
