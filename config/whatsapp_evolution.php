<?php

/*
 * Conexión de WhatsApp por QR (Evolution API), la misma del Pro 9.
 *
 * Dos formas de llegar a Evolution, se elige con WHATSAPP_EVOLUTION_MODE:
 *   - 'buho':      el proxy de Buho (bot-proxy.buho.xyz), como el Pro 9.
 *                  Credenciales BUHO_PROXY_CLIENT_ID / BUHO_PROXY_CLIENT_SECRET.
 *   - 'evolution': un Evolution API propio. EVOLUTION_API_URL + EVOLUTION_API_KEY.
 *
 * Sin credenciales la conexión no aparece y nada cambia: los avisos siguen
 * por el driver que la tienda ya tenía. Las credenciales van SOLO en el .env.
 */
return [
    'mode' => env('WHATSAPP_EVOLUTION_MODE', 'buho'),

    'buho' => [
        'url'           => env('BUHO_PROXY_URL', 'https://bot-proxy.buho.xyz'),
        'client_id'     => env('BUHO_PROXY_CLIENT_ID'),
        'client_secret' => env('BUHO_PROXY_CLIENT_SECRET'),
    ],

    'evolution' => [
        'url'     => env('EVOLUTION_API_URL'),
        'api_key' => env('EVOLUTION_API_KEY'),
    ],

    'timeout' => (int) env('WHATSAPP_EVOLUTION_TIMEOUT', 30),
];
