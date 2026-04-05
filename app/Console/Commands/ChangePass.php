<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ChangePass extends Command
{
    protected $signature = 'passwords';
    protected $description = 'Cambia las contraseñas de los tenants';

    public function handle()
    {
        $sites = \Hyn\Tenancy\Models\Website::all();

        foreach ($sites as $site) {
            $tenancyKey = config('tenancy.key');

            if ($tenancyKey === null) {
                $contra = md5(sprintf('%s.%d', config('app.key'), $site->id));
            } else {
                $contra = md5(sprintf('%d.%s.%s.%s', $site->id, $site->uuid, (string) $site->created_at, $tenancyKey));
            }

            // Usar prepared statements en vez de interpolación directa
            $username = $site->uuid;
            $host = '127.0.0.1';

            try {
                // ALTER USER con prepared statement
                DB::statement("ALTER USER ?@? IDENTIFIED BY ?", [$username, $host, $contra]);
                $this->info("Password actualizado para {$username}");
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() === 'HY000') {
                    DB::statement("CREATE USER ?@? IDENTIFIED BY ?", [$username, $host, $contra]);
                    $this->info("Usuario creado: {$username}");
                }
            }

            // Privilegios — GRANT no soporta prepared statements en MySQL,
            // pero uuid viene del sistema (no del usuario), así que sanitizamos con regex
            $dbName = preg_replace('/[^a-zA-Z0-9_-]/', '', $site->uuid);
            $safeUser = preg_replace('/[^a-zA-Z0-9_-]/', '', $username);

            try {
                DB::statement("CREATE USER IF NOT EXISTS `{$safeUser}`@`{$host}` IDENTIFIED BY ?", [$contra]);
                DB::statement("GRANT ALL PRIVILEGES ON `{$dbName}`.* TO `{$safeUser}`@`{$host}`");
                $this->info("Privilegios otorgados para {$safeUser} en {$dbName}");
            } catch (\Throwable $e) {
                $this->error("Error: {$e->getMessage()}");
            }
        }

        $this->alert("Proceso terminado");
    }
}
