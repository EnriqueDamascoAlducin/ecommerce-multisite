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

    /*
    |--------------------------------------------------------------------------
    | Openpay (México)
    |--------------------------------------------------------------------------
    |
    | Cobro en efectivo (Paynet/tiendas) vía cargo. Sólo se ofrece cuando hay
    | merchant_id + private_key. Las notificaciones se autentican por Basic Auth
    | (webhook_user/password) si están configurados. Sandbox por defecto.
    |
    */

    'openpay' => [
        'merchant_id' => env('OPENPAY_MERCHANT_ID'),
        'private_key' => env('OPENPAY_PRIVATE_KEY'),
        'public_key' => env('OPENPAY_PUBLIC_KEY'),
        'base_url' => env('OPENPAY_BASE_URL'), // null = se deriva del modo (sandbox/live)
        'webhook_user' => env('OPENPAY_WEBHOOK_USER'),
        'webhook_password' => env('OPENPAY_WEBHOOK_PASSWORD'),
    ],

];
