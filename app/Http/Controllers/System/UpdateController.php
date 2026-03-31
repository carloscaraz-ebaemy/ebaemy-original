<?php

namespace App\Http\Controllers\System;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Support\Facades\Artisan;

class UpdateController extends Controller
{
    public function index()
    {
        return view('system.update.index');
    }

    public function version()
    {
        try {
            $id = new Process(['git', 'describe', '--tags']);
            $id->setWorkingDirectory(base_path());
            $id->run();
            $res_id = $id->getOutput();
            return json_encode($res_id);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function branch()
    {
        try {
            $process = new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);
            $process->setWorkingDirectory(base_path());
            $process->setEnv(['GIT_DIR' => base_path('.git')]);
            $process->run();
            if (!$process->isSuccessful()) {
                // Intentar agregar safe.directory automáticamente
                $fix = new Process(['git', 'config', '--global', '--add', 'safe.directory', base_path()]);
                $fix->run();
                // Reintentar
                $process = new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);
                $process->setWorkingDirectory(base_path());
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
            }
            $output = $process->getOutput();
            return json_encode($output);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function pull($branch)
    {
        $chown = new Process(['chown', '-R' ,'ssh/']);
        $chown->run();

        $checkout = new Process(['git', 'checkout', '.']);
        $checkout->run();

        $process = new Process(['git',  'pull',  'origin', $branch]);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        $output = $process->getOutput();

        $fetch = new Process(['git',  'fetch']);
        $fetch->run();

        return json_encode($output);
    }

    public function artisanMigrate()
    {
        try {
            $output = Artisan::call('migrate', ['--force' => true]);
            $log = Artisan::output();
            return response()->json(['status' => 'success', 'output' => $output, 'log' => $log]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function artisanTenancyMigrate()
    {
        try {
            $output = Artisan::call('tenancy:migrate', ['--force' => true]);
            $log = Artisan::output();
            return response()->json(['status' => 'success', 'output' => $output, 'log' => $log]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function artisanClear()
    {
        $cacheclear = Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');
        return json_encode($cacheclear);
    }

    public function composerInstall()
    {
        $process = new Process(['composer' , 'install' , '-d', base_path()]);
        $process->run();
        $output = $process->getOutput();

        $chmod = new Process(['chmod', '-R' ,'777' , '../vendor/mpdf/mpdf']);
        $chmod->run();

        return json_encode($output);
    }

    public function keygen()
    {
        //genero ssh
        // $process = new Process(['chmod +x ../script-ssh.sh','sh ../script-ssh.sh']);
        // $process->run();
        // if (!$process->isSuccessful()) {
        //     throw new ProcessFailedException($process);
        // }
        // $output = $process->getOutput();

        //genero ssh sin validar
        //ssh-keygen -t rsa -q -P "" -f ../id_rsa


        // copio ssh a contenedor
        //docker cp archivo.txt facturadorpro31_fpm1_1:/root/.ssh/

        //eliminar la clave creada para evitar conflictos con el pull
        // rm ../id_*

        /* alternativa
        $process = new Process('sh /folder_name/file_name.sh');
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        echo $process->getOutput();
        */



        // return json_encode($output);
    }

    public function changelog() {
        try {
            $path = base_path('CHANGELOG.md');
            if (!File::exists($path)) {
                return '<p>No hay changelog disponible.</p>';
            }
            $file = File::get($path);
            // Convertir manualmente si Markdown facade falla (incompatibilidad commonmark)
            try {
                return Markdown::convertToHtml($file);
            } catch (\Throwable $e) {
                return '<pre>' . e($file) . '</pre>';
            }
        } catch (\Throwable $e) {
            return '<p>Error al leer changelog: ' . e($e->getMessage()) . '</p>';
        }
    }
}
