<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => env('SES_REGION', 'us-east-1'),
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => App\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI', '/ecommerce/auth/google/callback'),
    ],

    // Validación de RUC contra SUNAT (usado por RucValidationService).
    // Sin url configurada, el service solo valida formato y marca las
    // solicitudes como requires_manual_review. Provider sugerido: apis.net.pe.
    'ruc_validation' => [
        'url'   => env('RUC_VALIDATION_API_URL'),
        'token' => env('RUC_VALIDATION_API_TOKEN'),
    ],

    // Canales de soporte expuestos en flujos públicos (form /seller/register,
    // CTA "solicitar activación de tienda virtual" para clientes preexistentes).
    // Se referencian desde vistas blade via config('services.support.*').
    'support' => [
        'email'       => env('SUPPORT_EMAIL', 'soporte@ebaemy.com'),
        'whatsapp'    => env('SUPPORT_WHATSAPP'),      // Ej: 51999999999 (solo números con código país)
        'help_url'    => env('SUPPORT_HELP_URL'),      // Opcional: https://ebaemy.com/ayuda
    ],

    // MercadoPago — pasarela del checkout marketplace (multi-vendor).
    // En sandbox: usar credenciales TEST-* del panel MP del vendedor.
    // En producción: credenciales APP_USR-* (live).
    // El webhook_secret se valida contra el header x-signature.
    'mercadopago' => [
        'access_token'    => env('MP_ACCESS_TOKEN'),
        'public_key'      => env('MP_PUBLIC_KEY'),
        'webhook_secret'  => env('MP_WEBHOOK_SECRET'),
        'sandbox'         => (bool) env('MP_SANDBOX', false),
    ],

];
