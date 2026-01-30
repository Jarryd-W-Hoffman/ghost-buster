<?php

namespace Jarryd\GhostBuster\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Jarryd\GhostBuster\Support\IgnoreMatcher;

class TrackRouteUsage
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($response->getStatusCode() < 400 && $route = $request->route()) {
            $routeName = $route->getName() ?? $route->uri();
            $method = $request->method();

            if (IgnoreMatcher::shouldIgnore($routeName)) {
                return $response;
            }

            $driver = config('ghost-buster.driver', 'database');

            match ($driver) {
                'cache' => $this->logToCache($method, $routeName),
                default => $this->logToDatabase($method, $routeName),
            };
        }

        return $response;
    }

    protected function logToDatabase(string $method, string $routeName): void
    {
        DB::table('route_usage')->updateOrInsert([
            'route_name' => $routeName,
            'method' => $method,
        ], [
            'last_used_at' => now(),
        ]);
    }

    protected function logToCache(string $method, string $routeName): void
    {
        $prefix = config('ghost-buster.cache_prefix', 'ghost_buster:hit:');

        $cacheKey = "{$prefix}{$method}:{$routeName}";

        Cache::forever($cacheKey, now()->timestamp);
    }
}
