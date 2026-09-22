<?php

namespace App\Services\Security\Detectors;

use App\Services\Security\Alert;
use App\Services\Security\Contracts\DetectorModule;
use App\Services\Security\ScanContext;
use App\Services\Security\Severity;
use App\Services\Security\Support\AccessLogReader;
use App\Services\Security\Support\IntegrityMonitor;

/**
 * Modulo 3 — Ciberseguridad de la tienda y del servidor.
 *
 * Lee el access log de nginx/OpenResty de forma incremental y busca:
 *   - Inyeccion SQL, XSS, path traversal e inyeccion de comandos
 *   - Sondeo de rutas sensibles (/.env, /.git, /phpmyadmin, backups)
 *   - User-agents de herramientas de escaneo
 *   - Muchas respuestas 4xx desde una misma IP
 *   - Fuerza bruta contra el login y trafico excesivo por minuto
 *
 * Y ademas vigila el SHA-256 de los archivos del checkout y de pagos.
 * Nada de esto escribe en el servidor: solo lee y avisa.
 */
class WebAttackDetector implements DetectorModule
{
    /** Patrones de ataque en la URL, ya decodificada. */
    private const PAYLOADS = [
        'sql_injection' => [
            '/\bunion\s+(all\s+)?select\b/i',
            '/\bselect\b.{1,80}\bfrom\b.{1,80}\bwhere\b/i',
            '/\b(or|and)\s+[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d+/i',
            '/\b(sleep|benchmark)\s*\(/i',
            '/\binformation_schema\b/i',
            '/\b(load_file|into\s+outfile|into\s+dumpfile)\b/i',
            '/\bdrop\s+table\b/i',
            '/[\'"]\s*;\s*--/',
        ],
        'xss' => [
            '/<\s*script\b/i',
            '/javascript\s*:/i',
            '/\bon(error|load|mouseover|focus)\s*=/i',
            '/document\s*\.\s*cookie/i',
            '/<\s*iframe\b/i',
            '/<\s*img[^>]+onerror/i',
        ],
        'path_traversal' => [
            '/\.\.[\/\\\\]/',
            '/\/etc\/(passwd|shadow|hosts)/i',
            '/\bproc\/self\/environ\b/i',
            '/[a-z]:\\\\windows\\\\/i',
        ],
        'command_injection' => [
            '/[;|&]\s*(cat|ls|id|whoami|uname|wget|curl|nc|bash|sh|python|perl)\b/i',
            '/\$\(\s*[a-z]/i',
            '/`[a-z][^`]{1,40}`/i',
            '/\b(system|exec|shell_exec|passthru|popen)\s*\(/i',
        ],
    ];

    private const LABELS = [
        'sql_injection'     => 'Inyeccion SQL',
        'xss'               => 'Cross-site scripting (XSS)',
        'path_traversal'    => 'Path traversal',
        'command_injection' => 'Inyeccion de comandos',
    ];

    public function key(): string   { return 'web_attacks'; }
    public function label(): string { return 'Ciberseguridad del servidor'; }
    public function scope(): string { return 'system'; }

    public function detect(ScanContext $context): array
    {
        $alerts = $this->integrityAlerts($context);

        $cfg        = $context->config;
        $candidates = (array) $cfg->get('web_attacks.access_log_paths', []);
        $path       = AccessLogReader::resolvePath($candidates);

        if ($path === null) {
            $alerts[] = new Alert(
                module: $this->key(),
                type: 'access_log_ausente',
                severity: Severity::MEDIA,
                title: 'El agente no encuentra ningun access log legible',
                recommendation: 'Ajusta `web_attacks.access_log_paths` en storage/app/security-agent/config.json a la ruta real del log de OpenResty y da permiso de lectura al usuario que corre PHP. Mientras tanto, la deteccion de escaneos y ataques web esta ciega.',
                evidence: ['rutas_probadas' => $candidates],
                tenant: null,
                dedupeKey: 'system:web_attacks:missing_access_log',
            );

            return $alerts;
        }

        $reader = new AccessLogReader($context->state, md5($path));
        $result = $reader->read($path, (int) $cfg->get('web_attacks.max_lines', 200000));

        $whitelist = (array) $cfg->get('web_attacks.whitelist_ips', []);
        $entries   = array_values(array_filter(
            $result['entries'],
            fn ($e) => !in_array($e['ip'], $whitelist, true)
        ));

        if ($result['truncated']) {
            $alerts[] = new Alert(
                module: $this->key(),
                type: 'log_truncado',
                severity: Severity::MEDIA,
                title: 'El access log crecio mas de lo que el agente procesa en una corrida',
                recommendation: 'Sube `web_attacks.max_lines` o acorta el intervalo del cron. Un crecimiento asi de brusco tambien puede ser en si mismo un ataque de volumen: revisa el trafico.',
                evidence: ['lineas_leidas' => $result['lines'], 'archivo' => $path],
                tenant: null,
                dedupeKey: 'system:web_attacks:log_truncated',
            );
        }

        if (!$entries) {
            return $alerts;
        }

        return array_merge(
            $alerts,
            $this->payloadAlerts($context, $entries),
            $this->sensitivePathAlerts($context, $entries),
            $this->scannerAgentAlerts($context, $entries),
            $this->scanning4xxAlerts($context, $entries),
            $this->loginBruteForceAlerts($context, $entries),
            $this->rateLimitAlerts($context, $entries),
        );
    }

    // ── Ataques en la URL ────────────────────────────────────────────────────

    private function payloadAlerts(ScanContext $context, array $entries): array
    {
        $hits = [];

        foreach ($entries as $entry) {
            $target = $this->decode($entry['path']) . ' ' . $this->decode((string) ($entry['referer'] ?? ''));

            foreach (self::PAYLOADS as $type => $patterns) {
                foreach ($patterns as $pattern) {
                    if (!preg_match($pattern, $target)) continue;

                    $key = $type . '|' . $entry['ip'];
                    $hits[$key]['type']      = $type;
                    $hits[$key]['ip']        = $entry['ip'];
                    $hits[$key]['count']     = ($hits[$key]['count'] ?? 0) + 1;
                    $hits[$key]['samples'][] = [
                        'path'   => mb_substr($entry['path'], 0, 300),
                        'status' => $entry['status'],
                        'hora'   => $entry['time']->toDateTimeString(),
                        'agente' => mb_substr((string) $entry['agent'], 0, 150),
                    ];
                    // Un 2xx significa que el servidor RESPONDIO al payload.
                    $hits[$key]['succeeded'] = ($hits[$key]['succeeded'] ?? false) || $entry['status'] < 400;

                    continue 2;
                }
            }
        }

        $alerts = [];

        foreach ($hits as $hit) {
            $critical = $hit['succeeded'] && in_array($hit['type'], ['sql_injection', 'command_injection'], true);
            $label    = self::LABELS[$hit['type']];

            $alerts[] = new Alert(
                module: $this->key(),
                type: $hit['type'],
                severity: $critical ? Severity::CRITICA : Severity::ALTA,
                title: sprintf('%s: %d intento(s) desde %s%s', $label, $hit['count'], $hit['ip'], $critical ? ' CON respuesta 2xx/3xx' : ''),
                recommendation: $critical
                    ? "El servidor respondio sin error a un payload de {$label}. Bloquea {$hit['ip']} en el firewall AHORA, revisa `laravel.log` de esa franja, valida que no se hayan exfiltrado datos y confirma la integridad del codigo (el agente ya vigila los hashes del checkout)."
                    : "Bloquea {$hit['ip']} en el firewall. Los intentos fueron rechazados, pero es sondeo dirigido: revisa que las rutas tocadas validen sus parametros.",
                evidence: [
                    'ip'        => $hit['ip'],
                    'intentos'  => $hit['count'],
                    'respondio' => $hit['succeeded'],
                    'muestras'  => array_slice($hit['samples'], 0, 5),
                ],
                tenant: null,
                dedupeKey: "system:web_attacks:{$hit['type']}:{$hit['ip']}",
            );
        }

        return $alerts;
    }

    // ── Rutas sensibles ──────────────────────────────────────────────────────

    private function sensitivePathAlerts(ScanContext $context, array $entries): array
    {
        $sensitive = (array) $context->config->get('web_attacks.sensitive_paths', []);
        if (!$sensitive) return [];

        $hits = [];

        foreach ($entries as $entry) {
            $path = strtolower($this->decode($entry['path']));

            foreach ($sensitive as $needle) {
                if (!str_contains($path, strtolower($needle))) continue;

                $ip = $entry['ip'];
                $hits[$ip]['count']      = ($hits[$ip]['count'] ?? 0) + 1;
                $hits[$ip]['paths'][]    = mb_substr($entry['path'], 0, 200);
                $hits[$ip]['exposed']    = ($hits[$ip]['exposed'] ?? false) || $entry['status'] < 400;
                break;
            }
        }

        $alerts = [];

        foreach ($hits as $ip => $hit) {
            $exposed = (bool) ($hit['exposed'] ?? false);

            $alerts[] = new Alert(
                module: $this->key(),
                type: 'rutas_sensibles',
                severity: $exposed ? Severity::CRITICA : Severity::MEDIA,
                title: sprintf(
                    '%s sondeo %d ruta(s) sensible(s)%s',
                    $ip,
                    $hit['count'],
                    $exposed ? ' y alguna RESPONDIO con exito' : ''
                ),
                recommendation: $exposed
                    ? "Una ruta que deberia estar cerrada devolvio 2xx/3xx. Cierrala en nginx de inmediato, rota TODAS las credenciales de .env (base de datos, Culqi, MercadoPago, tokens de marketplaces) y bloquea {$ip}."
                    : "Bloquea {$ip}. El sondeo fue rechazado, pero confirma en nginx que /.env, /.git y /phpmyadmin devuelven 404 y no 403 con contenido.",
                evidence: [
                    'ip'     => $ip,
                    'rutas'  => array_slice(array_unique($hit['paths']), 0, 10),
                    'total'  => $hit['count'],
                ],
                tenant: null,
                dedupeKey: "system:web_attacks:sensitive_paths:{$ip}",
            );
        }

        return $alerts;
    }

    // ── User-agents de herramientas de escaneo ───────────────────────────────

    private function scannerAgentAlerts(ScanContext $context, array $entries): array
    {
        $signatures = array_map('strtolower', (array) $context->config->get('web_attacks.scanner_user_agents', []));
        if (!$signatures) return [];

        $hits = [];

        foreach ($entries as $entry) {
            $agent = strtolower((string) $entry['agent']);
            if ($agent === '') continue;

            foreach ($signatures as $signature) {
                if (!str_contains($agent, $signature)) continue;

                $key = $entry['ip'] . '|' . $signature;
                $hits[$key]['ip']        = $entry['ip'];
                $hits[$key]['tool']      = $signature;
                $hits[$key]['count']     = ($hits[$key]['count'] ?? 0) + 1;
                $hits[$key]['agent']     = mb_substr((string) $entry['agent'], 0, 200);
                break;
            }
        }

        $alerts = [];

        foreach ($hits as $hit) {
            $alerts[] = new Alert(
                module: $this->key(),
                type: 'escaner_conocido',
                severity: Severity::ALTA,
                title: "La herramienta de escaneo «{$hit['tool']}» esta analizando el sitio desde {$hit['ip']}",
                recommendation: "Bloquea {$hit['ip']} en el firewall. Nadie navega con {$hit['tool']}: es reconocimiento previo a un ataque. Si el escaneo es tuyo (auditoria contratada), agrega esa IP a `web_attacks.whitelist_ips`.",
                evidence: [
                    'ip'          => $hit['ip'],
                    'herramienta' => $hit['tool'],
                    'peticiones'  => $hit['count'],
                    'user_agent'  => $hit['agent'],
                ],
                tenant: null,
                dedupeKey: "system:web_attacks:scanner:{$hit['ip']}:{$hit['tool']}",
            );
        }

        return $alerts;
    }

    // ── Muchas 4xx desde una IP ──────────────────────────────────────────────

    private function scanning4xxAlerts(ScanContext $context, array $entries): array
    {
        $threshold = (int) $context->config->get('web_attacks.scanner_404.threshold', 30);
        $window    = (int) $context->config->get('web_attacks.scanner_404.window_minutes', 15);
        $since     = $context->now->subMinutes($window);

        $counts = [];

        foreach ($entries as $entry) {
            if ($entry['status'] < 400 || $entry['status'] >= 500) continue;
            if ($entry['time'] < $since) continue;

            $counts[$entry['ip']]['n'] = ($counts[$entry['ip']]['n'] ?? 0) + 1;
            $counts[$entry['ip']]['paths'][mb_substr($entry['path'], 0, 120)] = true;
        }

        $alerts = [];

        foreach ($counts as $ip => $data) {
            if ($data['n'] < $threshold) continue;

            $alerts[] = new Alert(
                module: $this->key(),
                type: 'escaneo_4xx',
                severity: Severity::ALTA,
                title: "{$ip} genero {$data['n']} respuestas 4xx en {$window} minutos",
                recommendation: "Es un escaneo de directorios. Bloquea {$ip} o aplica limite de tasa en nginx para esa IP. Revisa la lista de rutas por si alguna existe de verdad y no deberia ser publica.",
                evidence: [
                    'ip'              => $ip,
                    'respuestas_4xx'  => $data['n'],
                    'ventana_minutos' => $window,
                    'rutas'           => array_slice(array_keys($data['paths']), 0, 15),
                ],
                tenant: null,
                dedupeKey: "system:web_attacks:scan4xx:{$ip}",
            );
        }

        return $alerts;
    }

    // ── Fuerza bruta contra el login (desde el log web) ──────────────────────

    private function loginBruteForceAlerts(ScanContext $context, array $entries): array
    {
        $threshold = (int) $context->config->get('web_attacks.login_brute_force.threshold', 20);
        $window    = (int) $context->config->get('web_attacks.login_brute_force.window_minutes', 15);
        $since     = $context->now->subMinutes($window);

        $counts = [];

        foreach ($entries as $entry) {
            if ($entry['method'] !== 'POST') continue;
            if ($entry['time'] < $since) continue;

            $path = strtolower($entry['path']);
            if (!preg_match('#/(login|admin/login|iniciar-sesion)(\?|$)#', $path)) continue;

            $counts[$entry['ip']] = ($counts[$entry['ip']] ?? 0) + 1;
        }

        $alerts = [];

        foreach ($counts as $ip => $count) {
            if ($count < $threshold) continue;

            $alerts[] = new Alert(
                module: $this->key(),
                type: 'fuerza_bruta_login_web',
                severity: Severity::ALTA,
                title: "{$ip} envio {$count} intentos de login en {$window} minutos",
                recommendation: "Bloquea {$ip} en el firewall y cruza esta IP con el modulo de ingreso no autorizado para ver si alguno de los intentos tuvo exito. El throttle de la aplicacion es de 3 intentos / 5 min: un volumen asi significa que estan rotando la cuenta objetivo.",
                evidence: ['ip' => $ip, 'intentos' => $count, 'ventana_minutos' => $window],
                tenant: null,
                dedupeKey: "system:web_attacks:login_bf:{$ip}",
            );
        }

        return $alerts;
    }

    // ── Trafico excesivo por minuto ──────────────────────────────────────────

    private function rateLimitAlerts(ScanContext $context, array $entries): array
    {
        $limit  = (int) $context->config->get('web_attacks.rate_limit.requests_per_minute', 300);
        $buckets = [];

        foreach ($entries as $entry) {
            $bucket = $entry['ip'] . '|' . $entry['time']->format('Y-m-d H:i');
            $buckets[$bucket] = ($buckets[$bucket] ?? 0) + 1;
        }

        $peaks = [];

        foreach ($buckets as $bucket => $count) {
            if ($count < $limit) continue;

            [$ip, $minute] = explode('|', $bucket, 2);

            if (!isset($peaks[$ip]) || $count > $peaks[$ip]['count']) {
                $peaks[$ip] = ['count' => $count, 'minute' => $minute];
            }
        }

        $alerts = [];

        foreach ($peaks as $ip => $peak) {
            $alerts[] = new Alert(
                module: $this->key(),
                type: 'trafico_excesivo',
                severity: Severity::ALTA,
                title: "{$ip} hizo {$peak['count']} peticiones en un solo minuto (limite: {$limit})",
                recommendation: "Puede ser scraping del catalogo o un intento de saturacion. Aplica `limit_req` en nginx para esa IP. Si es un bot legitimo (Google, Meta), agregalo a `web_attacks.whitelist_ips`.",
                evidence: ['ip' => $ip, 'peticiones' => $peak['count'], 'minuto' => $peak['minute'], 'limite' => $limit],
                tenant: null,
                dedupeKey: "system:web_attacks:rate:{$ip}",
            );
        }

        return $alerts;
    }

    // ── Integridad de archivos ───────────────────────────────────────────────

    private function integrityAlerts(ScanContext $context): array
    {
        if (!$context->config->get('web_attacks.integrity.enabled', true)) {
            return [];
        }

        $monitor = new IntegrityMonitor($context->state);
        $result  = $monitor->check(
            (array) $context->config->get('web_attacks.integrity.files', []),
            (array) $context->config->get('web_attacks.integrity.globs', []),
        );

        if ($result['baseline'] || (!$result['changed'] && !$result['added'] && !$result['removed'])) {
            return [];
        }

        $alerts = [];

        if ($result['changed']) {
            $files = array_column($result['changed'], 'file');

            $alerts[] = new Alert(
                module: $this->key(),
                type: 'integridad_checkout',
                severity: Severity::CRITICA,
                title: sprintf('Cambio el contenido de %d archivo(s) criticos de checkout/pagos', count($files)),
                recommendation: 'Si NO acabas de desplegar, trata esto como un skimmer de tarjetas: compara los archivos con `git diff`, saca el sitio de linea si confirmas codigo ajeno, rota las llaves de Culqi y MercadoPago y avisa a tu adquirente. Si el cambio si es tuyo, acepta la nueva linea base con `php artisan security:scan --accept-integrity`.',
                evidence: ['archivos' => $result['changed']],
                tenant: null,
                dedupeKey: 'system:web_attacks:integrity:' . md5(implode('|', $files)),
            );
        }

        if ($result['added'] || $result['removed']) {
            $alerts[] = new Alert(
                module: $this->key(),
                type: 'integridad_inventario',
                severity: Severity::ALTA,
                title: 'Aparecieron o desaparecieron archivos de la lista vigilada',
                recommendation: 'Un archivo nuevo dentro del checkout es la via habitual para inyectar un skimmer; uno que desaparece puede ser un borrado de huellas. Revisa con `git status` y, si el cambio es legitimo, acepta la linea base con `--accept-integrity`.',
                evidence: ['nuevos' => $result['added'], 'eliminados' => $result['removed']],
                tenant: null,
                dedupeKey: 'system:web_attacks:integrity_inventory:' . md5(implode('|', array_merge($result['added'], $result['removed']))),
            );
        }

        return $alerts;
    }

    /** Decodifica la URL dos veces: los escaneres codifican el payload doble. */
    private function decode(string $value): string
    {
        $once = urldecode($value);

        return urldecode($once);
    }
}
