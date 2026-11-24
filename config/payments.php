<?php

return [

    // Nom du setting (table settings) qui désigne l'opérateur actif pour les nouveaux paiements
    'active_setting' => 'PAYMENT_ACTIVE_PROVIDER',

    // Durée de vie d'un paiement en attente avant d'être considéré comme expiré
    'pending_ttl_minutes' => 30,

    // Tentatives d'un remboursement avant de le marquer en échec et d'alerter
    'refund_max_attempts' => 5,

    // Liens de retour vers les apps après la page de paiement de l'opérateur
    'return_deeplinks' => [
        'customers' => env('APP_PAYMENT_RETURN_DEEPLINK_CUSTOMER'),
        'drivers' => env('APP_PAYMENT_RETURN_DEEPLINK_DRIVER'),
    ],

    'providers' => [
        'jeko' => [
            'class' => App\Payments\Gateways\JekoGateway::class,
            'base_url' => env('JEKO_BASE_URL', 'https://api.jeko.africa'),
            'api_key' => env('JEKO_API_KEY'),
            'api_key_id' => env('JEKO_API_KEY_ID'),
            'store_id' => env('JEKO_STORE_ID'),
            'webhook_secret' => env('JEKO_WEBHOOK_SECRET'),
            'max_amount' => 2000000,
            'timeout' => 15,
        ],
    ],
];
