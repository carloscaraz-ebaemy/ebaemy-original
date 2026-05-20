<?php

return [
    /*
     * Identidad VAPID. subject debe ser un mailto: o URL de contacto.
     * Las keys se generan UNA vez con `php artisan push:generate-vapid`
     * y se pegan en .env (NO commitear la private key).
     */
    'subject'     => env('VAPID_SUBJECT', 'mailto:soporte@ebaemy.com'),
    'public_key'  => env('VAPID_PUBLIC_KEY', ''),
    'private_key' => env('VAPID_PRIVATE_KEY', ''),
];
