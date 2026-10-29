# 👻 Ghost Buster

### Stop guessing which routes are safe to delete.

`Ghost Buster` silently monitors your application's traffic and identifies **"Ghost Routes"**—routes that are defined in your code but haven't been touched by a real user in weeks or months.

## Why Ghost Buster?

As Laravel applications grow, they naturally accumulate "Ghost Routes"—deprecated marketing pages, old API versions, or forgotten webhooks. Deleting them usually feels like playing Minesweeper because you lack usage data.

**Ghost Buster is route-agnostic.** Whether you use `routes/web.php`, modular route files, or third-party package routes, it hooks directly into the Laravel Router. If a route is registered in your app, Ghost Buster tracks it.

Ghost Buster gives you the **data-driven confidence** to prune your codebase without the "broken-link" anxiety.

## How It Works

Ghost Buster uses a "Listen, then Compare" approach:

1. **The Tracker:** A lightweight middleware (attached to your `web` and `api` groups by default) logs every successful request (status code below 400). It captures the route name (or URI) and the HTTP method.
2. **The Comparison:** When you run the `bust` command, the package reflects on your **live application router**. It compares every registered route against the usage data in your storage.
3. **The Result:** Any route present in your code but missing (or stale) in the usage logs is flagged as a Ghost.

## Features

* 🚀 **Universal Tracking:** Monitors routes in your `web` and `api` groups out of the box (custom route files and package routes included), or every route with `track_all_routes`.
* ⚡ **High Performance:** A single upsert per hit, an optional write throttle, or a Redis-backed cache driver for high-traffic environments.
* 🔢 **Hit Counts:** Records how often each route is used, so rarely used routes stand out from busy ones.
* 🛡️ **Smart Ignoring:** Easily exclude internal tools (Telescope, Horizon) or specific URI patterns.
* 📊 **Zero-Impact:** Tracking happens *after* the response is generated, so your users never feel a delay.

## Requirements

* PHP 8.3+
* Laravel 12 or 13

## Installation

Install the package via Composer:

```bash
composer require jarryd/ghost-buster
```

Run the migrations to create the usage table:

```bash
php artisan migrate
```

(Optional) Publish the configuration file:

```bash
php artisan vendor:publish --tag="ghost-buster-config"
```

## Configuration

In `config/ghost-buster.php`, you can customize the storage and tracking behavior.

### 1. Driver

```php
'driver' => env('GHOST_BUSTER_DRIVER', 'database'),
```

* **Supported:** `"database"` or `"cache"`
* **`database`** – Logs route usage in real-time. Ideal for small to medium apps.
* **`cache`** – Temporarily stores hits in Redis/Memcached. Best for high-performance apps to minimize database I/O.

### 2. Sync Frequency (Cache Driver Only)

```php
'sync_frequency' => 'hourly',
```

* **Purpose:** Determines how often cached route hits are flushed to the database.
* **Format:** Must be a valid [`Illuminate\Console\Scheduling\Event`](https://laravel.com/docs/scheduling#schedule-frequency-options) frequency method (e.g., `hourly`, `daily`, `twiceDaily`).
* **Note:** This setting has no effect if the `database` driver is active.

### 3. Ignored Route Patterns

```php
'ignore_patterns' => [
    'name:telescope*',
    'name:storage.*',
    'name:horizon*',
    'uri:_debugbar*',
    'uri:up',
],
```

* **Purpose:** Exclude specific routes from tracking.
* **Pattern types:**

  * `name:pattern` → matches route names
  * `uri:pattern` → matches route URIs
  * `pattern` (no prefix) → matches both name and URI
* **Wildcards (`*`)** are supported for flexible matching.
* **Use Case:** Perfect for ignoring internal tools, admin panels, or development utilities that don't represent "real" user traffic.

### Configuration Reference (Quick View)

| Setting           | Description                                             | Allowed Values / Example                                   | Notes                                                                                                             |
| ----------------- | ------------------------------------------------------- | ---------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `driver`          | Storage driver for route usage logs                     | `"database"` (default), `"cache"`                          | `database` logs hits immediately, `cache` temporarily stores hits.                                                |
| `sync_frequency`  | How often cached route hits are flushed to the database | `"hourly"`, `"daily"`, `"twiceDaily"`, `"everyTenMinutes"` | Only applies if `driver = cache`. Must be a valid Laravel scheduler frequency method.                             |
| `ignore_patterns` | Routes to exclude from tracking                         | `['name:telescope*', 'uri:test/*', 'storage.local']`       | Supports wildcards (`*`). Typed patterns: `name:` → route name, `uri:` → route URI, unprefixed → auto match both. |
| `cache_prefix`    | Prefix for cache keys holding route hits                | `"ghost_buster:hit:"`                                      | Only applies if `driver = cache`. Change it if several apps share a cache store.                                   |
| `middleware_groups` | Middleware groups the tracker is attached to          | `['web', 'api']`                                           | Routes outside these groups are not tracked. Groups that don't exist in your app are skipped.                     |
| `track_all_routes` | Register the tracker as global middleware              | `false` (default), `true`                                  | When `true`, every route is tracked and `middleware_groups` is ignored.                                           |
| `throttle`        | Seconds to skip repeat writes for the same route/method | `0` (default, off), `60`                                   | Only applies if `driver = database`. Uses a cache flag, so only worth it with a fast cache store (e.g. Redis). Hit counts become "at most one per window". |

## Usage

### 1. Identify Ghost Routes

After letting the package run for a few days, run the `bust` command to see which routes have been inactive:

```bash
# Find routes untouched for the last 30 days (default)
php artisan ghost:bust

# Find routes untouched for the last 90 days
php artisan ghost:bust --days=90

# Ignore extra patterns for this run only (same syntax as ignore_patterns)
php artisan ghost:bust --except="name:admin.*" --except="uri:webhooks/*"

# Machine-readable output, e.g. for CI or scripts
php artisan ghost:bust --json
```

**Example Output:**

```text
Checking for routes unused in the last 30 days...
Found 3 potentially dead routes:

+----------------------+---------+--------------+------+
| Route Name / URI     | Methods | Last Used    | Hits |
+----------------------+---------+--------------+------+
| api/v1/old-endpoint  | GET     | 2 months ago | 412  |
| temporary-promo-page | GET     | Never        | 0    |
| user/profile/legacy  | POST    | 5 months ago | 3    |
+----------------------+---------+--------------+------+
```

Routes that the tracking middleware doesn't run on (e.g. outside `middleware_groups`) have no usage data, so they are left out of the report and counted in a warning instead. Enable `track_all_routes` to include them.

### 2. High-performance Syncing (Cache Driver only)

If you are using the `cache` driver, hits are stored in your cache for speed. You must sync them to the database to view them in the report:

```bash
php artisan ghost:sync
```

*Note: Ghost Buster automatically schedules this command to run hourly when the cache driver is active.*

## Security

Ghost Buster only logs the **Route Name/URI**, **Method**, **Timestamp**, and a **hit count**. It never stores request payloads, headers, or PII (Personally Identifiable Information).

## Testing

To run the package's test suite:

```bash
composer test
```

The suite uses in-memory SQLite by default. To run it against MySQL or Postgres, set `DB_CONNECTION` (`mysql`/`pgsql`) along with `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`. CI covers PHP 8.3–8.5 on Laravel 12 and 13, plus MySQL and Postgres.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
