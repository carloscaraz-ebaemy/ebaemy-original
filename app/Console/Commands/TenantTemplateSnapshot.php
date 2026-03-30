<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Genera un snapshot SQL del esquema tenant para acelerar la creación de nuevos tenants.
 *
 * Sin snapshot: provisionar un tenant = correr 1027+ migraciones individualmente (lento).
 * Con snapshot:  provisionar un tenant = importar 1 archivo SQL (segundos).
 *
 * Flujo:
 *   1. Crea (o actualiza) la base de datos "tenant_template" en MySQL.
 *   2. Corre todas las migraciones de database/migrations/tenant sobre ella.
 *   3. Vuelca el esquema + tabla migrations a storage/tenant_schema_snapshot.sql.
 *
 * Uso:
 *   php artisan tenant:snapshot                  -- crea/actualiza snapshot
 *   php artisan tenant:snapshot --regenerate     -- elimina template y lo recrea desde cero
 *   php artisan tenant:snapshot --dry-run        -- solo reporta estado, no modifica nada
 *
 * Integración:
 *   Al estar el snapshot en storage/, el listener ApplySchemaSnapshot lo importará
 *   automáticamente cuando se cree un nuevo tenant (vía Events\Database\Created).
 *   Después de cada deploy con nuevas migraciones, ejecutar este comando para
 *   mantener el snapshot actualizado.
 */
class TenantTemplateSnapshot extends Command
{
    protected $signature = 'tenant:snapshot
                            {--regenerate : Eliminar la DB template y crearla desde cero}
                            {--dry-run    : Solo mostrar estado actual sin modificar nada}
                            {--template=  : Nombre de la DB template (default: tenant_template)}';

    protected $description = 'Genera un snapshot SQL del esquema tenant para acelerar el provisionamiento';

    private string $templateDb;
    private array  $sysConfig;

    public function handle(): int
    {
        $this->templateDb = $this->option('template') ?: (env('TENANT_TEMPLATE_DB', 'tenant_template'));
        $this->sysConfig  = config('database.connections.system');

        if ($this->option('dry-run')) {
            return $this->dryRun();
        }

        if ($this->option('regenerate')) {
            $this->dropTemplateDb();
        }

        $this->ensureTemplateDb();
        $this->runMigrationsOnTemplate();
        $snapshotPath = $this->dumpSnapshot();

        $this->info("✔  Snapshot guardado en: {$snapshotPath}");
        $this->info("   Tamano: " . $this->humanBytes(filesize($snapshotPath)));
        $this->newLine();
        $this->line("Los nuevos tenants usarán este snapshot automáticamente.");
        $this->line("Recuerde ejecutar <comment>tenant:snapshot</comment> después de cada deploy con nuevas migraciones.");

        return 0;
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function dryRun(): int
    {
        $snapshotPath = $this->snapshotPath();
        $this->info("=== Dry-run: tenant:snapshot ===");
        $this->line("Template DB : {$this->templateDb}");
        $this->line("Snapshot    : {$snapshotPath}");

        // Verificar existencia de template DB
        $exists = $this->templateDbExists();
        $this->line("Template DB existe: " . ($exists ? '<info>SI</info>' : '<comment>NO</comment>'));

        if ($exists) {
            // Contar migraciones aplicadas
            try {
                $count = DB::connection('tenant_template_temp')->table('migrations')->count();
                $this->line("Migraciones en template: {$count}");
            } catch (\Throwable $e) {
                $this->line("(no se pudo leer tabla migrations: {$e->getMessage()})");
            }
        }

        // Verificar snapshot
        if (file_exists($snapshotPath)) {
            $mtime = date('Y-m-d H:i:s', filemtime($snapshotPath));
            $size  = $this->humanBytes(filesize($snapshotPath));
            $this->line("Snapshot existente: <info>{$size}</info>, generado: {$mtime}");
        } else {
            $this->line("Snapshot: <comment>NO existe</comment>");
        }

        // Contar migraciones pendientes
        $migrationFiles = count(glob(database_path('migrations/tenant/*.php')));
        $this->line("Archivos de migración en disco: {$migrationFiles}");

        // Verificar binarios
        $mysqldump = $this->findBinary('mysqldump');
        $this->line("mysqldump: " . ($mysqldump ?: '<comment>NO encontrado</comment>'));

        return 0;
    }

    private function dropTemplateDb(): void
    {
        $this->warn("Eliminando DB template '{$this->templateDb}'...");
        $escaped = $this->escapeSqlIdentifier($this->templateDb);
        DB::connection('system')->statement("DROP DATABASE IF EXISTS {$escaped}");
        $this->line("   DB eliminada.");
    }

    private function ensureTemplateDb(): void
    {
        if (!$this->templateDbExists()) {
            $this->line("Creando DB template '{$this->templateDb}'...");
            $escaped = $this->escapeSqlIdentifier($this->templateDb);
            DB::connection('system')->statement(
                "CREATE DATABASE {$escaped} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
        } else {
            $this->line("DB template '{$this->templateDb}' ya existe.");
        }

        // Registrar conexión temporal
        config(['database.connections.tenant_template_temp' => array_merge(
            $this->sysConfig,
            ['database' => $this->templateDb]
        )]);
        DB::purge('tenant_template_temp');
    }

    private function runMigrationsOnTemplate(): void
    {
        $path = database_path('migrations/tenant');

        // Contar migraciones ya aplicadas
        $alreadyRan = 0;
        try {
            $alreadyRan = DB::connection('tenant_template_temp')
                ->table('migrations')
                ->count();
        } catch (\Throwable $e) {
            // tabla migrations no existe aún, normal en primera ejecución
        }

        $totalFiles = count(glob("{$path}/*.php"));
        $this->line("Corriendo migraciones sobre template ({$alreadyRan}/{$totalFiles} ya aplicadas)...");

        /** @var \Illuminate\Database\Migrations\Migrator $migrator */
        $migrator = app('migrator');
        $migrator->setConnection('tenant_template_temp');

        $bar = $this->output->createProgressBar($totalFiles - $alreadyRan);
        $bar->start();

        // Usar el repositorio del migrador para trackear progreso
        if (!$migrator->repositoryExists()) {
            app('migration.repository')->setSource('tenant_template_temp');
            app('migration.repository')->createRepository();
        }

        $migrator->run($path, ['pretend' => false]);
        $bar->finish();
        $this->newLine();

        $applied = DB::connection('tenant_template_temp')->table('migrations')->count();
        $this->line("   Migraciones en template: <info>{$applied}</info>");
    }

    private function dumpSnapshot(): string
    {
        $snapshotPath = $this->snapshotPath();
        $mysqldump    = $this->findBinary('mysqldump');

        if (!$mysqldump) {
            throw new \RuntimeException(
                'No se encontró mysqldump. ' .
                'Configura MYSQL_BIN_DIR en .env o añade mysql al PATH.'
            );
        }

        $this->line("Generando dump SQL...");

        $cfgFile = $this->writeTempCredentials($this->sysConfig);

        try {
            // Parte 1: solo schema (--no-data) + tabla migrations (con datos)
            $schemaCmd = sprintf(
                '"%s" --defaults-extra-file="%s" --no-create-db --skip-comments --no-data "%s"',
                $mysqldump,
                $cfgFile,
                $this->templateDb
            );

            $migrationsDataCmd = sprintf(
                '"%s" --defaults-extra-file="%s" --no-create-db --skip-comments --no-create-info "%s" migrations',
                $mysqldump,
                $cfgFile,
                $this->templateDb
            );

            $schemaSql     = $this->runShell($schemaCmd);
            $migrationsSql = $this->runShell($migrationsDataCmd);

            $header = sprintf(
                "-- Tenant schema snapshot\n-- Generated: %s\n-- Migrations: %d\n-- DO NOT EDIT MANUALLY\n\n",
                now()->toIso8601String(),
                DB::connection('tenant_template_temp')->table('migrations')->count()
            );

            file_put_contents($snapshotPath, $header . $schemaSql . "\n" . $migrationsSql);

        } finally {
            @unlink($cfgFile);
        }

        return $snapshotPath;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function templateDbExists(): bool
    {
        $result = DB::connection('system')->select(
            "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?",
            [$this->templateDb]
        );
        return !empty($result);
    }

    private function snapshotPath(): string
    {
        return storage_path('tenant_schema_snapshot.sql');
    }

    /**
     * Escribe un fichero .cnf temporal con credenciales MySQL.
     * Evita que user/password aparezcan en la línea de comando (visible en ps/tasklist).
     */
    private function writeTempCredentials(array $config): string
    {
        $cnfPath = tempnam(sys_get_temp_dir(), 'tenancy_snap_');
        file_put_contents($cnfPath, sprintf(
            "[client]\nuser=%s\npassword=%s\nhost=%s\nport=%s\n",
            $config['username'] ?? 'root',
            $config['password'] ?? '',
            $config['host']     ?? '127.0.0.1',
            $config['port']     ?? 3306
        ));
        chmod($cnfPath, 0600);
        return $cnfPath;
    }

    private function runShell(string $cmd): string
    {
        $output     = [];
        $returnCode = 0;
        exec($cmd . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException(
                "Comando falló (código {$returnCode}):\n" . implode("\n", $output)
            );
        }

        return implode("\n", $output);
    }

    private function findBinary(string $name): ?string
    {
        // 1. Override por env
        $envDir = env('MYSQL_BIN_DIR');
        if ($envDir) {
            $bin = rtrim($envDir, '/\\') . DIRECTORY_SEPARATOR . $name;
            if (PHP_OS_FAMILY === 'Windows') $bin .= '.exe';
            if (file_exists($bin)) return $bin;
        }

        // 2. Rutas comunes de Laragon en Windows
        if (PHP_OS_FAMILY === 'Windows') {
            $candidates = glob('C:\\laragon\\bin\\mysql\\*\\bin\\' . $name . '.exe') ?: [];
            if (!empty($candidates)) {
                return $candidates[0];
            }
        }

        // 3. Asumir que está en PATH
        $which = PHP_OS_FAMILY === 'Windows'
            ? shell_exec("where {$name} 2>NUL")
            : shell_exec("which {$name} 2>/dev/null");

        $found = trim((string) $which);
        return $found !== '' ? explode("\n", $found)[0] : null;
    }

    private function escapeSqlIdentifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    private function humanBytes(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) return "{$bytes} {$unit}";
            $bytes = (int) round($bytes / 1024);
        }
        return "{$bytes} GB";
    }
}
