# 👻 Ghost Buster

### Stop guessing which routes are safe to delete.

`Ghost Buster` silently monitors your application's traffic and identifies **"Ghost Routes"**—routes that are defined in your code but haven't been touched by a real user in weeks or months.

## Why Ghost Buster?

As Laravel applications grow, they naturally accumulate "Ghost Routes"—deprecated marketing pages, old API versions, or forgotten webhooks. Deleting them usually feels like playing Minesweeper because you lack usage data.

**Ghost Buster is route-agnostic.** Whether you use `routes/web.php`, modular route files, or third-party package routes, it hooks directly into the Laravel Router. If a route is registered in your app, Ghost Buster tracks it.

Ghost Buster gives you the **data-driven confidence** to prune your codebase without the "broken-link" anxiety.

## How It Works

Ghost Buster uses a "Listen, then Compare" approach:

1. **The Tracker:** A lightweight global middleware logs every successful request ( status code). It captures the route name (or URI) and the HTTP method.
2. **The Comparison:** When you run the `bust` command, the package reflects on your **live application router**. It compares every registered route against the usage data in your storage.
3. **The Result:** Any route present in your code but missing (or stale) in the usage logs is flagged as a Ghost.

## Features

* 🚀 **Universal Tracking:** Automatically monitors **all** routes, including custom files and package-defined routes.
* ⚡ **High Performance:** Uses `updateOrInsert` (upserts) for database efficiency or a Redis-backed cache driver for high-traffic environments.
* 🛡️ **Smart Ignoring:** Easily exclude internal tools (Telescope, Horizon) or specific URI patterns.
* 📊 **Zero-Impact:** Tracking happens *after* the response is generated, so your users never feel a delay.

## Installation

Install the package via Composer:

```bash
composer require jarryd/ghost-buster
```

Run the migration to create the usage table:

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

* **Options:** `database` or `cache`
* **`database`** – Logs route usage in real-time. Ideal for small to medium apps where simplicity is key.
* **`cache`** – Temporarily stores hits in Redis/Memcached. Best for high-performance apps to minimize database I/O.

### 2. Sync Frequency (Cache Driver Only)

```php
'sync_frequency' => 'hourly',
```

* **Purpose:** Determines how often cached route hits are flushed to the database.
* **Format:** Uses standard [Laravel Scheduler](https://laravel.com/docs/scheduling) syntax (e.g., `hourly`, `daily`, `everyTenMinutes`, `twiceDaily`).
* **Note:** This setting has no effect if the `database` driver is active.

### 3. Ignore Patterns

```php
'ignore_patterns' => [
    'telescope*',
    '_debugbar*',
    'horizon*',
    'admin/*',
],
```

* **Purpose:** Exclude specific routes from tracking using wildcards (`*`).
* **Use Case:** Perfect for ignoring internal tools, admin panels, or development utilities that don't represent "real" user traffic.

## Usage

### 1. Identify Ghost Routes

After letting the package run for a few days, run the `bust` command to see which routes have been inactive:

```bash
# Find routes untouched for the last 30 days (default)
php artisan ghost:bust

# Find routes untouched for the last 90 days
php artisan ghost:bust --days=90
```

**Example Output:**

```text
Checking for routes unused in the last 30 days...
Found 3 potentially dead routes:

+----------------------+---------+
| Route Name / URI     | Methods |
+----------------------+---------+
| api/v1/old-endpoint  | GET     |
| temporary-promo-page | GET     |
| user/profile/legacy  | POST    |
+----------------------+---------+
```

### 2. High-performance Syncing (Cache Driver only)

If you are using the `cache` driver, hits are stored in your cache for speed. You must sync them to the database to view them in the report:

```bash
php artisan ghost:sync
```

*Note: Ghost Buster automatically schedules this command to run hourly when the cache driver is active.*

## Security

Ghost Buster only logs the **Route Name/URI**, **Method**, and **Timestamp**. It never stores request payloads, headers, or PII (Personally Identifiable Information).

## Testing

To run the package's test suite:

```bash
./vendor/bin/phpunit
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
