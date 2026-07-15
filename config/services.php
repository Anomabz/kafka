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

    'pangkalan_data' => [
        'base_url' => env('PANGKALAN_DATA_BASE_URL', 'https://pangkalan-data.unsri.ac.id'),
        'email' => env('PANGKALAN_DATA_EMAIL'),
        'password' => env('PANGKALAN_DATA_PASSWORD'),
        'timeout' => (int) env('PANGKALAN_DATA_TIMEOUT', 30),
        'connect_timeout' => (int) env('PANGKALAN_DATA_CONNECT_TIMEOUT', 10),
        // ponytail: data mahasiswa/dosen/kerjasama disinkron jarang (harian/
        // mingguan/manual), bukan tiap jam — cache pendek cuma bikin request
        // pertama tiap jam kena query berat (lihat SaptoMahasiswaAsingRepository).
        // Naikkan ini kalau sync makin sering, atau php artisan cache:clear
        // manual sesudah sync kalau butuh data langsung fresh.
        'cache_ttl' => (int) env('PANGKALAN_DATA_CACHE_TTL', 86400),
        'token_cache_ttl' => (int) env('PANGKALAN_DATA_TOKEN_CACHE_TTL', 3300),
    ],

    'sso' => [
        'base_url' => env('SSO_BASE_URL', 'https://portal-sso.unsri.ac.id'),
        'realm' => env('SSO_REALM', 'unsri'),
        'client_id' => env('SSO_CLIENT_ID', 'app-sapto'),
        'client_secret' => env('SSO_CLIENT_SECRET'),
        // Must exactly match a redirect URI registered in Keycloak for this client.
        'redirect_uri' => env('SSO_REDIRECT_URI'),
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

];
