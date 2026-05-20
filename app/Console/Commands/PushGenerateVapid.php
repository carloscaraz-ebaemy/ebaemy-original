<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Genera el par de claves VAPID para Web Push. Se corre UNA sola vez.
 * Pega el resultado en el .env de producción (la private NO se commitea).
 *
 *   php artisan push:generate-vapid
 */
class PushGenerateVapid extends Command
{
    protected $signature   = 'push:generate-vapid';
    protected $description = 'Genera claves VAPID para Web Push (pegar en .env)';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->info('Claves VAPID generadas. Pega esto en tu .env y NO las compartas:');
        $this->line('');
        $this->line('VAPID_SUBJECT="mailto:soporte@ebaemy.com"');
        $this->line('VAPID_PUBLIC_KEY="' . $keys['publicKey'] . '"');
        $this->line('VAPID_PRIVATE_KEY="' . $keys['privateKey'] . '"');
        $this->line('');
        $this->warn('Luego: php artisan config:cache && restart php-fpm');
        $this->warn('La VAPID_PUBLIC_KEY es la que el frontend usa para suscribirse.');

        return self::SUCCESS;
    }
}
