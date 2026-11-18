<?php
/**
 * Genera EN EL SERVIDOR el PDF de las últimas notas de venta y comprobantes de
 * cada tienda y dice cuáles fallarían al imprimir. SOLO LECTURA de negocio:
 * no cambia documentos (sí puede escribir el PDF en storage, como al imprimir).
 *
 * Uso (desde la máquina de desarrollo):
 *   scp -q .claude/skills/ebaemy-errores/scripts/pdf-check.php ebaemy@ebaemy.com:/tmp/pdf-check.php
 *   ssh ebaemy@ebaemy.com 'cd /home/ebaemy/ebaemy/laravel && PDF_N=15 php artisan tinker --execute="require \"/tmp/pdf-check.php\";"; rm -f /tmp/pdf-check.php'
 *
 * Variables: PDF_N (cuántos por tienda y tipo, def. 10), PDF_TENANT (un fqdn).
 *
 * Por qué el manejador de errores: en la WEB Laravel convierte cualquier
 * warning/notice («Undefined property», «non-numeric value») en excepción → 500;
 * en tinker solo se imprimen como WARNING y el PDF «sale». Se replica el
 * comportamiento web, ignorando solo los deprecated (Laravel los registra).
 */

$N      = (int) (getenv('PDF_N') ?: 10);
$soloFq = getenv('PDF_TENANT') ?: null;
$env    = app(\Hyn\Tenancy\Environment::class);

set_error_handler(function ($no, $str, $file, $line) {
    if (in_array($no, [E_DEPRECATED, E_USER_DEPRECATED], true)) return true;
    throw new \ErrorException($str, 0, $no, $file, $line);
});

$fallos = 0; $ok = 0;
$hosts = \Hyn\Tenancy\Models\Hostname::with('website')->orderBy('fqdn')->get();

foreach ($hosts as $h) {
    if (!$h->website || ($soloFq && $h->fqdn !== $soloFq)) continue;
    try {
        $env->tenant($h->website);
        $c = \Illuminate\Support\Facades\DB::connection('tenant');
        $userId = $c->table('users')->orderBy('id')->value('id');
        if (!$userId) continue;
        auth()->loginUsingId($userId);

        $casos = [];
        foreach ($c->table('sale_notes')->orderByDesc('id')->limit($N)->get(['series', 'number', 'external_id']) as $n) {
            $casos[] = ['NV ' . $n->series . '-' . $n->number, fn ($f) => app(\App\Http\Controllers\Tenant\SaleNoteController::class)->toPrint($n->external_id, $f)];
        }
        if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('documents')) {
            foreach ($c->table('documents')->orderByDesc('id')->limit($N)->get(['series', 'number', 'external_id']) as $d) {
                $casos[] = ['CPE ' . $d->series . '-' . $d->number, fn ($f) => app(\App\Http\Controllers\Tenant\DownloadController::class)->toPrint('document', $d->external_id, $f)];
            }
        }

        foreach ($casos as [$etiqueta, $imprimir]) {
            foreach (['a4', 'ticket'] as $formato) {
                try {
                    $imprimir($formato);
                    $ok++;
                } catch (\Throwable $e) {
                    $fallos++;
                    $msg = preg_replace('#/home/ebaemy/ebaemy/laravel/#', '', $e->getMessage());
                    echo "FALLA | {$h->fqdn} | {$etiqueta} | {$formato} | " . mb_substr($msg, 0, 180) . PHP_EOL;
                }
            }
        }
    } catch (\Throwable $e) {
        echo "ERROR | {$h->fqdn} | " . mb_substr($e->getMessage(), 0, 160) . PHP_EOL;
    }
}

restore_error_handler();
echo "RESUMEN | ok={$ok} | fallos={$fallos}" . PHP_EOL;
