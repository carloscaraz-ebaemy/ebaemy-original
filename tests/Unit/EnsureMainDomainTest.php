<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureMainDomain;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * El marketplace y el SuperAdmin solo responden en el dominio principal.
 *
 * El grupo de rutas del sistema se declaraba con
 * `Route::domain($prefix . env('APP_URL_BASE'))`, y con la configuracion
 * cacheada —produccion— `env()` no lee el .env: quedaba `Route::domain(null)`,
 * que no restringe nada. Con DNS `*.ebaemy.com`, eso hacia que CUALQUIER
 * subdominio sirviera el marketplace entero con su canonical apuntandose a si
 * mismo. Lo vio el usuario en `myka.ebaemy.com/marketplace`, un subdominio de
 * una tienda que ya no existe.
 */
class EnsureMainDomainTest extends TestCase
{
    private function pasarPor(string $url)
    {
        config(['app.url' => 'https://ebaemy.com']);

        return (new EnsureMainDomain())->handle(
            Request::create($url, 'GET'),
            fn () => response('siguio', 200)
        );
    }

    public function test_el_dominio_principal_pasa(): void
    {
        $this->assertSame('siguio', $this->pasarPor('https://ebaemy.com/marketplace')->getContent());
    }

    public function test_www_tambien_pasa(): void
    {
        // Responde 200 hoy en produccion; cerrar el agujero no puede romperlo.
        $this->assertSame('siguio', $this->pasarPor('https://www.ebaemy.com/marketplace')->getContent());
    }

    public function test_un_subdominio_cualquiera_se_va_al_principal(): void
    {
        $r = $this->pasarPor('https://noexiste-xyz123.ebaemy.com/marketplace');

        $this->assertSame(301, $r->getStatusCode());
        $this->assertSame('https://ebaemy.com/marketplace', $r->headers->get('Location'));
    }

    public function test_el_subdominio_de_una_tienda_retirada_tambien(): void
    {
        $r = $this->pasarPor('https://myka.ebaemy.com/marketplace');

        $this->assertSame(301, $r->getStatusCode());
        $this->assertStringStartsWith('https://ebaemy.com/', $r->headers->get('Location'));
    }

    public function test_conserva_el_camino_y_los_parametros(): void
    {
        $r = $this->pasarPor('https://loquesea.ebaemy.com/marketplace?q=polo&sort=newest');

        $this->assertSame(
            'https://ebaemy.com/marketplace?q=polo&sort=newest',
            $r->headers->get('Location'),
            'un enlace compartido tiene que seguir llevando a lo mismo'
        );
    }

    public function test_localhost_pasa_para_no_romper_desarrollo(): void
    {
        $this->assertSame('siguio', $this->pasarPor('http://localhost/marketplace')->getContent());
    }

    public function test_sin_saber_el_dominio_principal_no_bloquea_nada(): void
    {
        // Preferible el agujero conocido a tumbar el sitio por una config
        // incompleta.
        config(['app.url' => '']);

        $r = (new EnsureMainDomain())->handle(
            Request::create('https://cualquiera.ebaemy.com/marketplace', 'GET'),
            fn () => response('siguio', 200)
        );

        $this->assertSame('siguio', $r->getContent());
    }
}
