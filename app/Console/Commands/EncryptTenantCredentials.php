<?php

namespace App\Console\Commands;

use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Encripta credenciales plain text en TODOS los tenants.
 *
 * Uso: php artisan tenants:encrypt-credentials
 * Verificar: php artisan tenants:encrypt-credentials --check
 */
class EncryptTenantCredentials extends Command
{
    protected $signature = 'tenants:encrypt-credentials {--check : Solo verificar}';
    protected $description = 'Encriptar credenciales plain text en todos los tenants';

    private array $tables = [
        'companies' => ['soap_password', 'integrated_query_client_secret', 'password_pse', 'ws_api_token', 'soap_sunat_password', 'api_sunat_secret'],
        'configurations' => ['smtp_password', 'token_private_culqui', 'token_public_culqui', 'token_apiruc', 'qrchat_app_key', 'qrchat_auth_key'],
        'courier_companies' => ['api_key', 'api_secret'],
    ];

    public function handle(): int
    {
        $checkOnly = $this->option('check');
        $websites = Website::all();

        $this->info("Procesando {$websites->count()} tenants...");

        foreach ($websites as $website) {
            $env = app(\Hyn\Tenancy\Environment::class);
            $env->tenant($website);

            $dbName = $website->uuid;
            $this->line("\n--- Tenant: {$dbName} ---");

            foreach ($this->tables as $table => $fields) {
                try {
                    if (!DB::connection('tenant')->getSchemaBuilder()->hasTable($table)) {
                        continue;
                    }

                    $rows = DB::connection('tenant')->table($table)->get();
                    $encrypted = 0;

                    foreach ($rows as $row) {
                        $updates = [];
                        foreach ($fields as $field) {
                            if (!isset($row->{$field}) || !$row->{$field}) continue;
                            if ($this->isAlreadyEncrypted($row->{$field})) continue;

                            if ($checkOnly) {
                                $this->warn("  {$table}.{$field} (id:{$row->id}): PLAIN TEXT");
                            } else {
                                $updates[$field] = Crypt::encryptString($row->{$field});
                                $encrypted++;
                            }
                        }
                        if (!empty($updates) && !$checkOnly) {
                            DB::connection('tenant')->table($table)->where('id', $row->id)->update($updates);
                        }
                    }

                    if (!$checkOnly && $encrypted > 0) {
                        $this->info("  {$table}: {$encrypted} campos encriptados");
                    }
                } catch (\Throwable $e) {
                    $this->error("  Error en {$table}: {$e->getMessage()}");
                }
            }
        }

        $this->info("\n" . ($checkOnly ? 'Verificación' : 'Encriptación') . ' completada.');
        return 0;
    }

    private function isAlreadyEncrypted(?string $value): bool
    {
        if (!$value) return false;
        try { Crypt::decryptString($value); return true; } catch (\Throwable $e) { return false; }
    }
}
