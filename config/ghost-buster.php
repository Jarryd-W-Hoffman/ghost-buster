<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ghost Buster Driver
    |--------------------------------------------------------------------------
    |
    | The driver that Ghost Buster will use to store route usage data.
    |
    */

    'driver' => env('GHOST_BUSTER_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Sync Frequency
    |--------------------------------------------------------------------------
    |
    | How often cached route usage should be flushed to the database.
    | This value is used by the scheduler or sync command.
    |
    */

    'sync_frequency' => 'hourly',

    /*
    |--------------------------------------------------------------------------
    | Ignored Route Patterns
    |--------------------------------------------------------------------------
    |
    | Route names or URIs matching these patterns will not be tracked.
    | You can specify whether the pattern applies to the route name or URI.
    |
    */

    'ignore_patterns' => [
        'name:telescope*',
        'name:storage.*',
        'name:horizon*',
        'uri:_debugbar*',
        'uri:up',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for storing route hits in your cache.
    | Useful if multiple apps share the same cache store.
    |
    */

    'cache_prefix' => 'ghost_buster:hit:',

    /*
    |--------------------------------------------------------------------------
    | Middleware Groups
    |--------------------------------------------------------------------------
    |
    | The middleware groups Ghost Buster should attach to.
    | By default, both 'web' and 'api' are tracked.
    |
    */

    'middleware_groups' => [
        'web',
        'api',
    ],

    /*
    |--------------------------------------------------------------------------
    | Track All Routes
    |--------------------------------------------------------------------------
    |
    | When enabled, the tracker is registered as global middleware so every
    | route is tracked, regardless of its middleware groups. When disabled,
    | routes outside the groups above are untracked and are left out of the
    | ghost:bust report, since there is no usage data for them.
    |
    */

    'track_all_routes' => env('GHOST_BUSTER_TRACK_ALL_ROUTES', false),

    /*
    |--------------------------------------------------------------------------
    | Write Throttle (Database Driver)
    |--------------------------------------------------------------------------
    |
    | The number of seconds to wait before recording another hit for the same
    | route and method. A cache flag is used to skip the database write within
    | this window, so it only helps when your cache store is faster than your
    | database (e.g. Redis). Hit counts become "at most one per window".
    | Set to 0 to record every request.
    |
    */

    'throttle' => env('GHOST_BUSTER_THROTTLE', 0),

];
