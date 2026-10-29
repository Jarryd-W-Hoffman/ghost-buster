<?php

namespace Jarryd\GhostBuster\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Jarryd\GhostBuster\Support\IgnoreMatcher;
use Jarryd\GhostBuster\Support\UsageRecorder;

class TrackRouteUsage
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    /**
     * Record the hit after the response has been sent to the client.
     */
    public function terminate(Request $request, $response): void
    {
        $route = $request->route();

        if (! $route || $response->getStatusCode() >= 400 || IgnoreMatcher::route($route)) {
            return;
        }

        $routeName = $route->getName() ?? $route->uri();
        $method = $request->method();

        match (config('ghost-buster.driver', 'database')) {
            'cache' => $this->logToCache($method, $routeName),
            default => $this->logToDatabase($method, $routeName),
        };
    }

    protected function logToDatabase(string $method, string $routeName): void
    {
        $throttle = (int) config('ghost-buster.throttle', 0);

        // Cache::add() only succeeds when the key is absent, so it doubles as the
        // "recorded recently?" check without a separate read.
        if ($throttle > 0 && ! Cache::add($this->cacheKey('throttle:', $method, $routeName), true, $throttle)) {
            return;
        }

        UsageRecorder::record($method, $routeName, now());
    }

    protected function logToCache(string $method, string $routeName): void
    {
        Cache::forever($this->cacheKey('', $method, $routeName), now()->timestamp);

        // Some stores (database, memcached) won't increment a missing key.
        $countKey = $this->cacheKey('count:', $method, $routeName);
        Cache::add($countKey, 0);
        Cache::increment($countKey);
    }

    protected function cacheKey(string $type, string $method, string $routeName): string
    {
        $prefix = config('ghost-buster.cache_prefix', 'ghost_buster:hit:');

        return "{$prefix}{$type}{$method}:{$routeName}";
    }
}
