<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Paiement des abonnements (mobile money via DexPay). Le mode sandbox
    // choisit l'URL de l'API ; les clés (pk_test_/sk_test_ ou
    // pk_live_/sk_live_) doivent correspondre au même environnement. La
    // clé secrète sert uniquement à vérifier la signature des webhooks.
    'dexpay' => [
        'public_key' => env('DEXPAY_PUBLIC_KEY'),
        'secret_key' => env('DEXPAY_SECRET_KEY'),
        'sandbox' => (bool) env('DEXPAY_SANDBOX', true),
        'base_url' => env('DEXPAY_BASE_URL') ?: (env('DEXPAY_SANDBOX', true)
            ? 'https://api-sandbox.dexpay.africa/api/v1'
            : 'https://api.dexpay.africa/api/v1'),
        // URL publique de notre webhook ; à défaut, déduite de la requête
        // qui crée la session (hôte public du backend derrière le proxy).
        'webhook_url' => env('DEXPAY_WEBHOOK_URL'),
        // Retour du client après paiement : l'application de la structure.
        'return_url' => env('DEXPAY_RETURN_URL') ?: env('FRONTEND_URL'),
    ],

    // Étape 9 : assistance IA. Clé absente => AiProvider résout vers
    // SimulatedAiProvider (comportement dégradé, pas d'appel réseau).
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
    ],

];
