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
    'hideDefaultLocaleInURL' => false,
    'localesOrder' => array_keys($supportedLocales),
    'localesMapping' => [],
    'utf8suffix' => env('LARAVELLOCALIZATION_UTF8SUFFIX', '.UTF-8'),
    'urlsIgnored' => [],
    'httpMethodsIgnored' => ['POST', 'PUT', 'PATCH', 'DELETE'],
];
