<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ghost Buster Driver
    |--------------------------------------------------------------------------
    |
    | The driver that Ghost Buster will use to store route usage data.
    |
    | Supported: "database", "cache"
    |
    */

    'driver' => env('GHOST_BUSTER_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Sync Frequency
    |--------------------------------------------------------------------------
    |
    | How often cached route usage should be flushed to the database.
    | This value can be used by your scheduler or sync command.
    |
    | Examples: "hourly", "daily", "twiceDaily", "weekly"
    |
    */

    'sync_frequency' => 'hourly',

    /*
    |--------------------------------------------------------------------------
    | Ignored Route Patterns
    |--------------------------------------------------------------------------
    |
    | Route names or URIs matching these patterns will not be tracked.
    | Wildcards (*) are supported.
    |
    */

    'ignore_patterns' => [
        'telescope*',
        '_debugbar*',
        'horizon*',
    ],

];
