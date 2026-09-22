<?php

namespace App\Services\Security\Output;

use App\Services\Security\Alert;
use App\Services\Security\Severity;
use Carbon\CarbonImmutable;

/**
 * Reporte HTML de la ultima corrida. Autocontenido (sin CSS externo) para que
 * se pueda abrir desde el servidor o adjuntar por correo.
 */
final class HtmlReport
{
    private const COLORS = [
        Severity::CRITICA => '#b91c1c',
        Severity::ALTA    => '#c2410c',
        Severity::MEDIA   => '#a16207',
        Severity::BAJA    => '#1d4ed8',
    ];

    public function __construct(private readonly string $path) {}

    public static function make(): self
    {
        $dir = storage_path('app/' . config('security-agent.storage_path', 'security-agent'));

        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        return new self($dir . DIRECTORY_SEPARATOR . 'report.html');
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @param  Alert[]  $alerts
     * @param  array    $summary  Resultado devuelto por SecurityAgent::run()
     */
    public function write(array $alerts, array $summary): string
    {
        file_put_contents($this->path, $this->render($alerts, $summary));

        return $this->path;
    }

    public function render(array $alerts, array $summary): string
    {
        $generated = CarbonImmutable::now(config('security-agent.timezone', 'America/Lima'));
        $counts    = array_fill_keys(Severity::all(), 0);

        foreach ($alerts as $alert) {
            $counts[strtoupper($alert->severity)] = ($counts[strtoupper($alert->severity)] ?? 0) + 1;
        }

        $cards = '';
        foreach (array_reverse(Severity::all()) as $severity) {
            $color  = self::COLORS[$severity];
            $cards .= '<div class="card" style="border-top:4px solid ' . $color . '">'
                . '<div class="num" style="color:' . $color . '">' . $counts[$severity] . '</div>'
                . '<div class="lab">' . $severity . '</div></div>';
        }

        $rows = '';
        foreach ($alerts as $alert) {
            $color    = self::COLORS[strtoupper($alert->severity)] ?? '#334155';
            $evidence = htmlspecialchars(
                json_encode($alert->evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                ENT_QUOTES
            );

            $rows .= '<article class="alert">'
                . '<header><span class="badge" style="background:' . $color . '">' . $this->e($alert->severity) . '</span>'
                . '<span class="mod">' . $this->e($alert->module) . ' · ' . $this->e($alert->type) . '</span>'
                . '<span class="when">' . $this->e($alert->detectedAt->format('d/m/Y H:i')) . '</span>'
                . '<span class="scope">' . $this->e($alert->tenant ?? 'sistema') . '</span></header>'
                . '<h3>' . $this->e($alert->title) . '</h3>'
                . '<p class="rec"><strong>Que hacer:</strong> ' . $this->e($alert->recommendation) . '</p>'
                . '<details><summary>Evidencia</summary><pre>' . $evidence . '</pre></details>'
                . '</article>';
        }

        if ($rows === '') {
            $rows = '<p class="ok">Sin alertas nuevas en esta corrida.</p>';
        }

        $errors = '';
        if (!empty($summary['errors'])) {
            $errors = '<section class="errors"><h2>Modulos con error</h2><ul>';
            foreach ($summary['errors'] as $error) {
                $errors .= '<li><strong>' . $this->e($error['module']) . '</strong>'
                    . ($error['tenant'] ? ' (' . $this->e($error['tenant']) . ')' : '')
                    . ' — ' . $this->e($error['error']) . '</li>';
            }
            $errors .= '</ul></section>';
        }

        $meta = sprintf(
            'Generado %s · %d tenant(s) revisados · %d alerta(s) repetida(s) omitida(s) · %d ms',
            $generated->format('d/m/Y H:i:s'),
            $summary['tenants'] ?? 0,
            $summary['suppressed'] ?? 0,
            $summary['duration_ms'] ?? 0
        );

        return <<<HTML
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Agente de Seguridad — Reporte</title>
<style>
 :root{--bg:#f8fafc;--fg:#0f172a;--mut:#64748b;--line:#e2e8f0;--card:#fff}
 @media (prefers-color-scheme:dark){:root{--bg:#0f172a;--fg:#e2e8f0;--mut:#94a3b8;--line:#1e293b;--card:#1e293b}}
 *{box-sizing:border-box}
 body{margin:0;padding:24px 16px;background:var(--bg);color:var(--fg);font:15px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif}
 .wrap{max-width:960px;margin:0 auto}
 h1{font-size:22px;margin:0 0 4px}
 .meta{color:var(--mut);font-size:13px;margin-bottom:20px}
 .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;margin-bottom:24px}
 .card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:14px;text-align:center}
 .num{font-size:28px;font-weight:700;line-height:1}
 .lab{color:var(--mut);font-size:12px;letter-spacing:.06em;margin-top:4px}
 .alert{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:16px;margin-bottom:12px}
 .alert header{display:flex;flex-wrap:wrap;gap:8px;align-items:center;font-size:12px;color:var(--mut);margin-bottom:8px}
 .badge{color:#fff;padding:2px 8px;border-radius:99px;font-weight:700;letter-spacing:.04em}
 .alert h3{margin:0 0 8px;font-size:16px;line-height:1.35}
 .rec{margin:0 0 10px}
 details summary{cursor:pointer;color:var(--mut);font-size:13px}
 pre{background:var(--bg);border:1px solid var(--line);border-radius:8px;padding:10px;overflow:auto;font-size:12px;margin:8px 0 0}
 .ok{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:24px;text-align:center;color:var(--mut)}
 .errors{margin-top:24px;border-top:1px solid var(--line);padding-top:12px;font-size:14px}
 .errors h2{font-size:15px}
</style></head>
<body><div class="wrap">
<h1>Agente de Seguridad y Deteccion de Riesgos</h1>
<p class="meta">{$meta}</p>
<div class="cards">{$cards}</div>
{$rows}
{$errors}
</div></body></html>
HTML;
    }

    private function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
