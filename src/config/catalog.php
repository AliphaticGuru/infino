<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Catalog media configuration
    |--------------------------------------------------------------------------
    |
    | Product media is stored on a dedicated filesystem disk. The public disk
    | is used by default in local development; the s3 disk can be selected for
    | production through the CATALOG_MEDIA_DISK environment variable.
    */

    'media' => [

        'disk' => env('CATALOG_MEDIA_DISK', 'public'),

        'allowed_disks' => ['public', 's3'],

        'directory' => 'catalog/products',

        'max_size_kb' => (int) env('CATALOG_MEDIA_MAX_SIZE_KB', 5120),

        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ],

        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],

        'max_width' => (int) env('CATALOG_MEDIA_MAX_WIDTH', 8000),

        'max_height' => (int) env('CATALOG_MEDIA_MAX_HEIGHT', 8000),
    ],

];
