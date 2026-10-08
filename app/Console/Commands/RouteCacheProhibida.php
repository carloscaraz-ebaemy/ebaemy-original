<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Bloquea `php artisan route:cache`, que en este proyecto ROMPE produccion.
 *
 * POR QUE NO SE PUEDE CACHEAR LAS RUTAS AQUI
 * ------------------------------------------
 * `routes/web.php` no registra un mapa de rutas: registra UNO DE DOS segun
 * quien pregunta. Mira `CurrentHostname` y, si el host es de un tenant,
 * registra las rutas del tenant; si no, las del sistema y el marketplace.
 * Eso es incompatible con cachear: la cache congela la rama que tocara en el
 * momento de generarla, y encima la genera la CLI, que no tiene host.
 *
 * Ademas `$app_url` sale de `env('APP_URL_BASE')`, y con la configuracion
 * cacheada —como esta en produccion— `env()` devuelve null. Asi que al
 * cachear queda `Route::domain(null)`: TODAS las rutas del sistema y del
 * marketplace pierden su restriccion de dominio.
 *
 * EL DANO REAL, visto en produccion el 2026-10-07
 * -----------------------------------------------
 * `https://<cualquier-tenant>.ebaemy.com/marketplace` empezo a servir el
 * marketplace central, con su `<link rel="canonical">` apuntandose a si mismo.
 * Son 17 copias del marketplace entero compitiendo en Google, cada una
 * declarandose la original. Se arreglo con `php artisan route:clear`.
 *
 * Ya estaba escrito que no se hiciera —en la skill de deploy y en
 * `scripts/deploy-orders-flow.sh`— y aun asi se ejecuto tres despliegues
 * seguidos. De ahi este comando: una nota se puede pasar por alto, un fallo
 * con codigo != 0 no.
 */
class RouteCacheProhibida extends Command
{
    protected $signature = 'route:cache';

    protected $description = 'PROHIBIDO en este proyecto — las rutas dependen del host y cachearlas rompe los subdominios';

    public function handle(): int
    {
        $this->newLine();
        $this->error('  route:cache esta PROHIBIDO en este proyecto.  ');
        $this->newLine();
        $this->line('  routes/web.php registra un mapa de rutas distinto segun el host que');
        $this->line('  pregunta (tenant vs dominio principal). Cachearlas congela una de las');
        $this->line('  dos ramas, y ademas deja Route::domain(null) porque con la config');
        $this->line('  cacheada env() ya no lee el .env.');
        $this->newLine();
        $this->line('  Lo que pasa si se hace: <tenant>.ebaemy.com/marketplace empieza a');
        $this->line('  servir el marketplace central, con canonical propio. 17 copias en');
        $this->line('  Google. Ocurrio el 2026-10-07.');
        $this->newLine();
        $this->info('  Para refrescar cache de despliegue, usa:');
        $this->line('      php artisan optimize:clear && php artisan config:cache && php artisan view:cache');
        $this->newLine();
        $this->comment('  Si alguna vez se arregla routes/web.php para no depender del host,');
        $this->comment('  borra app/Console/Commands/RouteCacheProhibida.php.');
        $this->newLine();

        return self::FAILURE;
    }
}
