<?php

$supportedLocales = [
        'en' => [
            'name' => 'English',
            'script' => 'Latn',
            'native' => 'English',
            'regional' => 'en_US',
        ],
        'ar' => [
            'name' => 'Arabic',
            'script' => 'Arab',
            'native' => 'العربية',
            'regional' => 'ar_AE',
        ],
        'ur' => [
            'name' => 'Urdu',
            'script' => 'Arab',
            'native' => 'اردو',
            'regional' => 'ur_PK',
        ],
    ];

return [
    'supportedLocales' => $supportedLocales,

    'useAcceptLanguageHeader' => false,
    'hideDefaultLocaleInURL' => true,
    'localesOrder' => array_keys($supportedLocales),
    'localesMapping' => [],
    'urlsIgnored' => [
        'verify-email',
        'verify-email/*',
        '*/verify-email',
        '*/verify-email/*',
        'login/social-callback',
        '*/login/social-callback',
        'post-payment-login/*',
        '*/post-payment-login/*',
    ],
    'httpMethodsIgnored' => ['POST', 'PUT', 'PATCH', 'DELETE'],
];
