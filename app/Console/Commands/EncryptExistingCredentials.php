<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Encripta credenciales existentes en la BD que ahora usan cast 'encrypted'.
 *
 * IMPORTANTE: Ejecutar UNA SOLA VEZ después de agregar los casts.
 * Si se ejecuta dos veces, los datos ya encriptados se corrompen.
 *
 * Uso: php artisan credentials:encrypt
 * Verificar: php artisan credentials:encrypt --check
 */
class EncryptExistingCredentials extends Command
{
    protected $signature = 'credentials:encrypt
                            {--check : Solo verificar estado sin modificar}
                            {--model= : Modelo específico a procesar (Company, Configuration, Client, CourierCompany)}';

    protected $description = 'Encriptar credenciales existentes en plain text';

    /** Mapeo: modelo → campos a encriptar */
    private array $models = [
        'Company' => [
            'table'      => null, // tabla tenant
            'connection' => 'tenant',
            'fields'     => ['soap_password', 'integrated_query_client_secret', 'password_pse', 'ws_api_token', 'soap_sunat_password', 'api_sunat_secret'],
        ],
        'Configuration' => [
            'table'      => 'configurations',
            'connection' => 'tenant',
            'fields'     => ['smtp_password', 'token_private_culqui', 'token_public_culqui', 'token_apiruc', 'qrchat_app_key', 'qrchat_auth_key', 'qr_api_apiKey'],
        ],
        'Client' => [
            'table'      => 'clients',
            'connection' => 'system',
            'fields'     => ['smtp_password'],
        ],
        'CourierCompany' => [
            'table'      => 'courier_companies',
            'connection' => 'tenant',
            'fields'     => ['api_key', 'api_secret'],
        ],
    ];

    public function handle(): int
    {
        $targetModel = $this->option('model');
        $checkOnly   = $this->option('check');

        $this->warn($checkOnly ? 'MODO VERIFICACIÓN (sin cambios)' : 'MODO ENCRIPTACIÓN');

        foreach ($this->models as $name => $config) {
            if ($targetModel && $targetModel !== $name) continue;

            $this->info("--- {$name} ---");

            if ($config['connection'] === 'tenant') {
                $this->warn("  ⚠ Modelo tenant: debe ejecutarse por cada tenant con tenant context activo.");
                $this->warn("  Usa: php artisan tenancy:run credentials:encrypt --model={$name}");
                continue;
            }

            // Modelos del sistema (clients)
            $this->processSystemModel($name, $config, $checkOnly);
        }

        $this->info($checkOnly ? 'Verificación completada.' : 'Encriptación completada.');
        return 0;
    }

    protected function processSystemModel(string $name, array $config, bool $checkOnly): void
    {
        $rows = DB::table($config['table'])->get();
        $encrypted = 0;
        $skipped   = 0;

        foreach ($rows as $row) {
            foreach ($config['fields'] as $field) {
                $value = $row->{$field} ?? null;
                if (!$value) {
                    $skipped++;
                    continue;
                }

                // Verificar si ya está encriptado (los valores encriptados empiezan con "eyJ")
                if ($this->isAlreadyEncrypted($value)) {
                    $skipped++;
                    if ($checkOnly) $this->line("  {$field} (row {$row->id}): YA ENCRIPTADO");
                    continue;
                }

                if ($checkOnly) {
                    $this->warn("  {$field} (row {$row->id}): PLAIN TEXT → necesita encriptación");
                } else {
                    DB::table($config['table'])
                        ->where('id', $row->id)
                        ->update([$field => Crypt::encryptString($value)]);
                    $encrypted++;
                    $this->line("  {$field} (row {$row->id}): encriptado");
                }
            }
        }

        $this->info("  Total: {$encrypted} encriptados, {$skipped} omitidos");
    }

    protected function isAlreadyEncrypted(?string $value): bool
    {
        if (!$value) return false;
        // Los valores encriptados por Laravel son base64 de un JSON con iv+value+mac
        try {
            Crypt::decryptString($value);
            return true; // Si se puede desencriptar, ya está encriptado
        } catch (\Throwable $e) {
            return false; // No se puede desencriptar → es plain text
        }
    }
}
