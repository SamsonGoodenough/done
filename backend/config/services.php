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

    // Movie/TV metadata, see docs/planning/07-external-integrations.md
    'tmdb' => [
        'key' => env('TMDB_API_KEY'),
        'base_url' => env('TMDB_BASE_URL', 'https://api.themoviedb.org/3'),
        'image_base_url' => env('TMDB_IMAGE_BASE_URL', 'https://image.tmdb.org/t/p/w500'),
    ],

    // Game metadata (IGDB), authenticated via a Twitch Developer app's
    // OAuth2 client-credentials flow, see docs/adr/0002-games-metadata-vendor-igdb-over-rawg.md
    'twitch' => [
        'client_id' => env('TWITCH_CLIENT_ID'),
        'client_secret' => env('TWITCH_CLIENT_SECRET'),
        'oauth_url' => env('TWITCH_OAUTH_URL', 'https://id.twitch.tv/oauth2/token'),
    ],

    'igdb' => [
        'base_url' => env('IGDB_BASE_URL', 'https://api.igdb.com/v4'),
    ],

    // Book metadata — no API key required.
    'open_library' => [
        'base_url' => env('OPEN_LIBRARY_BASE_URL', 'https://openlibrary.org'),
        'covers_base_url' => env('OPEN_LIBRARY_COVERS_BASE_URL', 'https://covers.openlibrary.org'),
    ],

];
