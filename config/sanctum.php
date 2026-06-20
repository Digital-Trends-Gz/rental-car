<?php

use Laravel\Sanctum\Sanctum;

$domains = explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1'));

// Add base domain and wildcard for subdomains if APP_URL is set
if ($appUrl = env('APP_URL')) {
    $host = parse_url($appUrl, PHP_URL_HOST);
    if ($host) {
        $domains[] = $host;
        $domains[] = '*.' . $host;
    }
}

// Dynamically add the current request host to support dynamic tenant subdomains
if (isset($_SERVER['HTTP_HOST'])) {
    $domains[] = $_SERVER['HTTP_HOST'];
}

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Typically, this includes your local and
    | production frontend URLs.
    |
    */

    'stateful' => array_values(array_unique(array_filter($domains))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value determines the number of minutes until an issued token will
    | be considered expired. If this value is null, personal access tokens
    | do not expire.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | Sanctum uses the following middleware to authenticate requests. You may
    | customize these as needed for your application.
    |
    */

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'verify_csrf_token' => Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
    ],

];
