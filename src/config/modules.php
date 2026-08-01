<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Modules Path
    |--------------------------------------------------------------------------
    */

    'path' => app_path('Modules'),

    /*
    |--------------------------------------------------------------------------
    | Module Directories
    |--------------------------------------------------------------------------
    */

    'directories' => [

        '',

        'Contracts',

        'Database',
        'Database/Factories',
        'Database/Migrations',
        'Database/Seeders',

        'Http',
        'Http/Controllers',
        'Http/Middleware',
        'Http/Requests',

        'Models',
        'Policies',

        'Providers',

        'Services',
        'Support',

        'Tests',

        'routes',
    ],
];