<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pasarela por defecto
    |--------------------------------------------------------------------------
    |
    | Código de la pasarela preseleccionada en el checkout. Debe estar entre las
    | registradas y disponibles (configuradas).
    |
    */

    'default' => env('PAYMENTS_DEFAULT', 'offline'),

    /*
    |--------------------------------------------------------------------------
    | Mercado Pago
    |--------------------------------------------------------------------------
    |
    | La pasarela sólo se ofrece en el checkout cuando hay un access token. El
    | webhook_secret habilita la verificación de firma de las notificaciones.
    |
    */

    'mercadopago' => [
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
        'base_url' => env('MERCADOPAGO_BASE_URL', 'https://api.mercadopago.com'),
    ],

];
