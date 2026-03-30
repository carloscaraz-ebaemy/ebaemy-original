<?php

namespace App\Listeners\Tenancy;

use Hyn\Tenancy\Events\Database\Created as DatabaseCreated;
use Illuminate\Support\Facades\Log;

/**
 * Importa el snapshot SQL del esquema tenant en la nueva base de datos del tenant,
 * justo después de que hyn/tenancy crea la base de datos vacía y ANTES de que
 * MigratesTenants intente correr las migraciones.
 *
 * Resultado: al llegar MigratesTenants, la tabla 'migrations' ya tiene todas las
 * entradas → no ejecuta ninguna migración → provisionamiento en segundos en vez
 * de minutos.
 *
 * Si el snapshot no existe, el listener se retira silenciosamente y el
 * provisionamiento normal (correr 1027+ migraciones) sigue su curso.
 *
 * Generar/actualizar el snapshot:
 *   php artisan tenant:snapshot
 */
class ApplySchemaSnapshot
{
    /**
     * @param  DatabaseCreated  $event  Contiene $event->config (array de conexión del tenant)
     *                                  y $event->website (modelo Website con uuid).
     */
    public function handle(DatabaseCreated $event): void
    {
        $snapshotPath = storage_path('tenant_schema_snapshot.sql');

        if (!file_exists($snapshotPath)) {
            // Snapshot no disponible; el provisionamiento normal tomará el control.
            return;
        }

        $config = $event->config;
        $dbName = $config['database'] ?? null;

        if (!$dbName) {
            Log::warning('[ApplySchemaSnapshot] No se pudo determinar el nombre de la DB del tenant.');
            return;
        }

        Log::info("[ApplySchemaSnapshot] Importando snapshot en DB [{$dbName}]...");

        try {
            $this->importSnapshot($config, $snapshotPath, $dbName);
            Log::info("[ApplySchemaSnapshot] Snapshot importado en [{$dbName}] correctamente.");
        } catch (\Throwable $e) {
            // No bloquear el provisionamiento — MigratesTenants lo completará igualmente.
            Log::error("[ApplySchemaSnapshot] Error importando snapshot en [{$dbName}]: {$e->getMessage()}", [
                'exception' => $e,
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function importSnapshot(array $config, string $snapshotPath, string $dbName): void
    {
        $mysql = $this->findMysqlBinary();

        if (!$mysql) {
            throw new \RuntimeException(
                'No se encontró el binario mysql. ' .
                'Configure MYSQL_BIN_DIR en .env o añada mysql al PATH del sistema.'
            );
        }

        $cfgFile = $this->writeTempCredentials($config);

        try {
            $cmd = sprintf(
                '"%s" --defaults-extra-file="%s" "%s"',
                $mysql,
                $cfgFile,
                $dbName
            );

            // Redirigir stdin desde el archivo SQL
            $descriptor = [
                0 => ['file', $snapshotPath, 'r'],
                1 => ['pipe', 'w'], // stdout
                2 => ['pipe', 'w'], // stderr
            ];

            $proc = proc_open($cmd, $descriptor, $pipes);

            if (!is_resource($proc)) {
                throw new \RuntimeException("No se pudo iniciar el proceso mysql.");
            }

            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($proc);

            if ($exitCode !== 0) {
                throw new \RuntimeException(
                    "mysql falló (código {$exitCode}): {$stderr}"
                );
            }

        } finally {
            @unlink($cfgFile);
        }
    }

    /**
     * Escribe un fichero .cnf temporal con las credenciales del tenant.
     * Evita que user/password aparezcan en la lista de procesos del SO.
     */
    private function writeTempCredentials(array $config): string
    {
        $cfgPath = tempnam(sys_get_temp_dir(), 'tenancy_snap_import_');
        file_put_contents($cfgPath, sprintf(
            "[client]\nuser=%s\npassword=%s\nhost=%s\nport=%s\n",
            $config['username'] ?? 'root',
            $config['password'] ?? '',
            $config['host']     ?? '127.0.0.1',
            $config['port']     ?? 3306
        ));
        chmod($cfgPath, 0600);
        return $cfgPath;
    }

    private function findMysqlBinary(): ?string
    {
        // 1. Override por env
        $envDir = env('MYSQL_BIN_DIR');
        if ($envDir) {
            $bin = rtrim($envDir, '/\\') . DIRECTORY_SEPARATOR . 'mysql';
            if (PHP_OS_FAMILY === 'Windows') $bin .= '.exe';
            if (file_exists($bin)) return $bin;
        }

        // 2. Rutas comunes de Laragon en Windows
        if (PHP_OS_FAMILY === 'Windows') {
            $candidates = glob('C:\\laragon\\bin\\mysql\\*\\bin\\mysql.exe') ?: [];
            if (!empty($candidates)) {
                return $candidates[0];
            }
        }

        // 3. Asumir que está en PATH
        $which = PHP_OS_FAMILY === 'Windows'
            ? shell_exec('where mysql 2>NUL')
            : shell_exec('which mysql 2>/dev/null');

        $found = trim((string) $which);
        if ($found === '') return null;

        return explode("\n", $found)[0];
    }
}
