<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => mb_rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         * The shared photo library's origin bytes (App\Models\LibraryPhoto).
         *
         * Deliberately absent from tenancy.filesystem.disks / root_override /
         * url_override: this disk must NOT be tenant-suffixed, because one
         * catalogue serves every tenant.
         *
         * Rendered pages never point here — AdoptLibraryPhoto copies bytes
         * onto the adopting tenant's `public` disk, which is what Curator's
         * Glide thumbnails and the page image URLs resolve against. The URL
         * exists only so the browse-and-adopt panels can show a photo NO
         * tenant has taken yet. It is an absolute URL on the central domain
         * (APP_URL), so the same <img> works from a tenant-domain panel, and
         * it is unauthenticated because every byte here is a stock photo the
         * provider already serves publicly.
         */
        'library' => [
            'driver' => 'local',
            'root' => storage_path('app/library'),
            'url' => mb_rtrim((string) env('APP_URL', 'http://localhost'), '/').'/library',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
        // The shared photo library, served straight off the central domain so
        // both panels can render a thumbnail of a photo no tenant has adopted
        // yet (see the 'library' disk).
        public_path('library') => storage_path('app/library'),
    ],

];
