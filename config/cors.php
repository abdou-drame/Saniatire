<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // Routes concernées par la politique CORS
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // PRODUCTION : remplacez [mondomaine] par votre vrai domaine.
    // Ne jamais utiliser ['*'] pour un backend médical — trop permissif.
    'allowed_origins' => [
        env('FRONTEND_URL', 'https://app.[mondomaine]'),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // true = obligatoire pour Sanctum cookie-based auth (cross-domain)
    'supports_credentials' => true,

];
