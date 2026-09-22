<?php

/*
|--------------------------------------------------------------------------
| Agente de Seguridad y Deteccion de Riesgos — valores por defecto
|--------------------------------------------------------------------------
|
| ESTE ARCHIVO NO SE EDITA EN EL DIA A DIA.
|
| Para cambiar umbrales, listas y horarios edita:
|     storage/app/security-agent/config.json
|
| Ese JSON se fusiona ENCIMA de estos valores (merge profundo), asi que solo
| necesitas escribir en el las claves que quieras cambiar. Genera una copia
| comentada de partida con:  php artisan security:scan --init-config
|
| Las CREDENCIALES nunca van aqui ni en el JSON: siempre en .env.
|
*/

return [

    // ── Ejecucion ─────────────────────────────────────────────────────────────
    'enabled'  => env('SECURITY_AGENT_ENABLED', true),
    'timezone' => 'America/Lima',

    // Conexion de SOLO LECTURA. Si es null se usa la conexion normal del tenant.
    // Recomendado: crear un usuario MySQL con GRANT SELECT y apuntar aqui.
    'read_connection' => env('SECURITY_AGENT_DB_CONNECTION'),

    // Modulos activos. Puedes apagar cualquiera sin tocar codigo.
    'modules' => [
        'unauthorized_access' => true,   // 5
        'web_attacks'         => true,   // 3
        'order_fraud'         => false,  // 1 — fase B
        'catalog_anomalies'   => false,  // 2 — fase B
        'marketplace_health'  => false,  // 4 — fase C
        'work_schedule'       => false,  // 6 — fase C
    ],

    // ── Deduplicacion y estado ────────────────────────────────────────────────
    'dedupe_hours' => 24,
    'storage_path' => 'security-agent',           // relativo a storage/app
    'retention'    => [
        'alerts_jsonl_days' => 90,
        'state_days'        => 180,
    ],

    // ── Salidas ───────────────────────────────────────────────────────────────
    'output' => [
        'console' => true,
        'jsonl'   => true,
        'html'    => true,
    ],

    // ── Notificaciones (solo ALTA y CRITICA) ─────────────────────────────────
    'notify' => [
        'min_severity' => 'ALTA',
        'telegram' => [
            'enabled' => env('SECURITY_AGENT_TELEGRAM_ENABLED', false),
            'token'   => env('SECURITY_AGENT_TELEGRAM_TOKEN'),
            'chat_id' => env('SECURITY_AGENT_TELEGRAM_CHAT_ID'),
        ],
        'mail' => [
            'enabled' => env('SECURITY_AGENT_MAIL_ENABLED', false),
            'to'      => array_values(array_filter(array_map('trim', explode(',', (string) env('SECURITY_AGENT_MAIL_TO', ''))))),
        ],
        'webhook' => [
            'enabled' => env('SECURITY_AGENT_WEBHOOK_ENABLED', false),
            'url'     => env('SECURITY_AGENT_WEBHOOK_URL'),   // Slack incoming webhook
        ],
    ],

    // ── Modulo 5: ingreso no autorizado ──────────────────────────────────────
    'unauthorized_access' => [
        // Lista blanca. Vacia = se acepta cualquier usuario activo de la tabla
        // `users` (util al arrancar; llenala en cuanto tengas el censo real).
        'authorized_emails' => [],
        'allowed_countries' => ['PE'],
        'brute_force' => [
            'failed_attempts' => 5,
            'window_minutes'  => 15,
        ],
        'password_spray' => [
            'distinct_users' => 4,
            'window_minutes' => 30,
        ],
        // Login exitoso precedido de N fallos contra la misma cuenta.
        'success_after_failures'  => 3,
        'impossible_travel_hours' => 4,
        // IPs internas que nunca deben generar alerta de "IP nueva".
        'trusted_ips' => ['127.0.0.1', '::1'],
        'geolocation' => [
            'enabled'    => true,
            'cache_days' => 30,
        ],
    ],

    // ── Modulo 3: ciberseguridad de la tienda/servidor ───────────────────────
    'web_attacks' => [
        // Rutas candidatas del access log. Se usa la primera que exista y sea
        // legible. Ajustala a tu servidor.
        'access_log_paths' => [
            '/usr/local/openresty/nginx/logs/access.log',
            '/var/log/openresty/access.log',
            '/var/log/nginx/access.log',
        ],
        'log_format' => 'combined',
        'max_lines'  => 200000,   // techo por corrida, para no comerse la RAM

        'scanner_404' => [
            'threshold'      => 30,   // respuestas 4xx desde una misma IP
            'window_minutes' => 15,
        ],
        'login_brute_force' => [
            'threshold'      => 20,   // POST al login desde una misma IP
            'window_minutes' => 15,
        ],
        'rate_limit' => [
            'requests_per_minute' => 300,  // scraping / DoS
        ],
        'sensitive_paths' => [
            '/.env', '/.git', '/wp-admin', '/wp-login.php', '/phpmyadmin',
            '/pma', '/adminer.php', '/config.php', '/backup', '/.aws',
            '/vendor/phpunit', '/server-status', '/.ssh', '/dump.sql',
        ],
        'scanner_user_agents' => [
            'sqlmap', 'nikto', 'nmap', 'wpscan', 'acunetix', 'nessus',
            'masscan', 'dirbuster', 'gobuster', 'zgrab', 'havij', 'netsparker',
        ],
        // IPs que nunca generan alerta (tu oficina, monitoreo, CDN).
        'whitelist_ips' => ['127.0.0.1', '::1'],

        // Monitoreo de integridad: SHA-256 de los archivos del checkout y pagos.
        // Un cambio inesperado aqui es la firma de un skimmer de tarjetas.
        'integrity' => [
            'enabled' => true,
            'files' => [
                'app/Services/Tenant/CheckoutService.php',
                'app/Services/Tenant/OrderPaymentSync.php',
                'app/Services/Tenant/PaymentVerification.php',
                'app/Services/System/MarketplaceCheckoutService.php',
                'app/Services/System/MercadoPagoService.php',
                'modules/Ecommerce/Http/Controllers/CulqiController.php',
            ],
            // Globs adicionales (por ejemplo el JS compilado del checkout).
            'globs' => [
                'public/build/assets/[Cc]heckout*.js',
                'public/build/assets/[Cc]art*.js',
            ],
        ],
    ],

    // ── Modulo 1: fraude en pedidos y pagos (fase B) ─────────────────────────
    'order_fraud' => [
        'lookback_hours' => 24,
        'thresholds'     => ['media' => 35, 'alta' => 60, 'critica' => 85],
        'scores' => [
            'amount_over_median'  => 20,
            'amount_over_max'     => 25,
            'ip_country_mismatch' => 25,
            'disposable_email'    => 20,
            'multi_order_same_ip' => 15,
            'card_shared'         => 30,
            'customer_velocity'   => 15,
            'night_purchase'      => 10,
        ],
        'median_multiplier' => 4,
        'max_amount'        => 5000,      // PEN
        'same_ip_orders'    => 3,
        'customer_velocity' => ['orders' => 3, 'window_hours' => 6],
        'night_hours'       => [0, 6],    // 00:00–05:59
        'disposable_domains' => [
            'mailinator.com', 'yopmail.com', 'tempmail.com', '10minutemail.com',
            'guerrillamail.com', 'trashmail.com', 'sharklasers.com',
            'getnada.com', 'maildrop.cc', 'throwawaymail.com', 'dispostable.com',
        ],
    ],

    // ── Modulo 2: errores de catalogo (fase B) ───────────────────────────────
    'catalog_anomalies' => [
        'min_margin_pct'    => 10,
        'price_drop_pct'    => 30,
        'price_rise_pct'    => 50,
        'cross_channel_pct' => 25,
        'critical_stock'    => 3,
        'ignore_item_ids'   => [],
    ],

    // ── Modulo 4: salud de cuentas en marketplaces (fase C) ──────────────────
    'marketplace_health' => [
        'limits' => [
            'claims_pct'           => 2,
            'cancellations_pct'    => 3,
            'late_shipment_pct'    => 5,
            'paused_listings'      => 10,
            'unanswered_questions' => 5,
        ],
        'degradation_pct' => 30,
    ],

    // ── Modulo 6: horarios de trabajo (fase C) ───────────────────────────────
    'work_schedule' => [
        'tolerance_minutes' => 15,
        'schedule' => [
            'mon' => ['08:00', '19:00'],
            'tue' => ['08:00', '19:00'],
            'wed' => ['08:00', '19:00'],
            'thu' => ['08:00', '19:00'],
            'fri' => ['08:00', '19:00'],
            'sat' => ['09:00', '13:00'],
            'sun' => null,   // no laborable
        ],
        // Feriados de Peru (AAAA-MM-DD). Editalos en el JSON cada ano.
        'holidays' => [
            '2026-01-01', '2026-04-02', '2026-04-03', '2026-05-01',
            '2026-06-07', '2026-06-29', '2026-07-23', '2026-07-28',
            '2026-07-29', '2026-08-06', '2026-08-30', '2026-10-08',
            '2026-11-01', '2026-12-08', '2026-12-09', '2026-12-25',
        ],
        // Turnos especiales: "correo" => ['mon' => ['14:00','23:00'], ...]
        'user_exceptions' => [],
        'sensitive_actions' => [
            'item_price_change', 'customer_export', 'item_delete',
            'permission_change', 'refund', 'bank_account_change', 'user_create',
        ],
        'sensitive_burst' => ['actions' => 20, 'window_minutes' => 60],
    ],
];
