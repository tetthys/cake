<?php

return [
    'policy_namespaces' => [
        'App\\Policies',
    ],

    // Production only by default (you can enable in staging if you want)
    'cache' => [
        'enabled' => env('CAKE_CACHE_ENABLED', null), // null => auto (production only)
        'prefix' => 'cake:policy-map:',
        'ttl_seconds' => 3600,
    ],
];
