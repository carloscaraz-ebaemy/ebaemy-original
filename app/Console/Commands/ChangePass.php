<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Ifsnop\Mysqldump as IMysqldump;
use Exception;

class ChangePass extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'passwords';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cambia las contraseñas';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $sites = \Hyn\Tenancy\Models\Website::all();
        $passwords = [];
        foreach($sites as $site){
            // Generar contraseña igual que Hyn Tenancy (DefaultPasswordGenerator)
            $tenancyKey = \Config::get('tenancy.key');
            if ($tenancyKey === null) {
                // Compatibilidad: usa app.key + id
                $contra = md5(sprintf('%s.%d', \Config::get('app.key'), $site->id));
            } else {
                // Fórmula oficial: id.uuid.created_at.key
                $contra = md5(sprintf('%d.%s.%s.%s', $site->id, $site->uuid, (string) $site->created_at, $tenancyKey));
            }
            $temp = [
                'username'=>$site->uuid,
                'password'=>$contra,
                'query'=> "ALTER USER `".$site->uuid."`@`127.0.0.1` IDENTIFIED BY '$contra' ;",
            ];
            $passwords[] = $temp;
            $this->line($temp['query'] );
            try{
                \DB::update( $temp['query'] );

            }catch (\Illuminate\Database\QueryException $e){
                if("HY000"==$e->getCode()){
                    $temp['query'] = "CREATE USER `".$site->uuid."`@`127.0.0.1` IDENTIFIED BY '$contra';";
                    $this->line($temp['query'] );
                    \DB::update( $temp['query'] );

                }
            }

            // Otorgar privilegios sobre la BD existente al usuario del tenant
            $dbName = $site->uuid; // Se asume que el nombre de la BD es el uuid del sitio
            foreach (['127.0.0.1'] as $host) {
                try {
                    // Asegurar que exista la cuenta para ese host (no falla si ya existe)
                    $ensure = "CREATE USER IF NOT EXISTS `{$site->uuid}`@`{$host}` IDENTIFIED BY '$contra';";
                    $this->line($ensure);
                    \DB::update($ensure);

                    // Otorgar privilegios sobre la BD
                    $grant = "GRANT ALL PRIVILEGES ON `{$dbName}`.* TO `{$site->uuid}`@`{$host}`;";
                    $this->line($grant);
                    \DB::update($grant);
                } catch (\Throwable $e) {
                    $this->line("Error asignando permisos");
                    $this->error("Error asignando permisos/creación de usuario {$site->uuid}@{$host} en BD {$dbName}: ".$e->getMessage());
                }
            }
            $this->info("Se ha ejecutado");
        }
        $this->alert("usuario: {$site->uuid} | contraseña: {$contra}");
        $this->alert("Proceso terminado");
    }
}
