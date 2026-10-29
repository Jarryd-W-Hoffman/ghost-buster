<?php

namespace Jarryd\GhostBuster\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\Router;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Jarryd\GhostBuster\Http\Middleware\TrackRouteUsage;
use Jarryd\GhostBuster\Support\IgnoreMatcher;

class GhostBusterCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghost:bust
        {--days=30 : The number of days of inactivity before a route is considered unused}
        {--except=* : Additional ignore patterns for this run (same syntax as ignore_patterns)}
        {--json : Output the ghost routes as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Identify and list unused routes in the application based on the inactivity period.';

    /**
     * Execute the console command.
     */
    public function handle(Router $router, HttpKernel $kernel): int
    {
        $days = $this->option('days');

        if (! is_numeric($days) || (int) $days != $days || (int) $days < 0) {
            $this->error('The --days option must be a whole number of 0 or more.');
            return Command::INVALID;
        }

        $days = (int) $days;
        $threshold = now()->subDays($days);
        $json = (bool) $this->option('json');

        if (! $json) {
            $this->info("Checking for routes unused in the last {$days} days...");
        }

        $trackedGlobally = method_exists($kernel, 'hasMiddleware') && $kernel->hasMiddleware(TrackRouteUsage::class);

        [$tracked, $untracked] = collect($router->getRoutes()->getRoutes())
            ->reject(fn ($route) => IgnoreMatcher::route($route, $this->option('except')))
            ->partition(fn ($route) => $trackedGlobally || $this->isTracked($router, $route));

        // Usage is keyed by method too, so unnamed routes sharing a URI
        // (e.g. GET users / POST users) are tracked independently.
        $usage = DB::table('route_usage')
            ->get(['route_name', 'method', 'hits', 'last_used_at'])
            ->keyBy(fn ($row) => "{$row->method}:{$row->route_name}");

        $ghosts = $tracked
            ->map(function ($route) use ($usage) {
                $name = $route->getName() ?? $route->uri();
                $rows = collect($route->methods())
                    ->map(fn ($method) => $usage->get("{$method}:{$name}"))
                    ->filter();

                $lastUsed = $rows->max('last_used_at');

                return [
                    'name' => $name,
                    'methods' => $route->methods(),
                    'last_used_at' => $lastUsed ? Carbon::parse($lastUsed) : null,
                    'hits' => (int) $rows->sum('hits'),
                ];
            })
            ->filter(fn ($route) => $route['last_used_at'] === null || $route['last_used_at']->lt($threshold))
            // Never-used routes first, then the longest-unused.
            ->sortBy(fn ($route) => $route['last_used_at']?->getTimestamp() ?? PHP_INT_MIN)
            ->values();

        if ($json) {
            $this->line(json_encode($ghosts->map(fn ($route) => [
                'name' => $route['name'],
                'methods' => $route['methods'],
                'last_used_at' => $route['last_used_at']?->toIso8601String(),
                'hits' => $route['hits'],
            ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        if ($untracked->isNotEmpty()) {
            $count = $untracked->count();
            $this->warn('Skipped ' . $count . ' ' . Str::plural('route', $count) . ' not covered by the tracking middleware (see middleware_groups / track_all_routes).');
        }

        if ($ghosts->isEmpty()) {
            $this->info('No ghosts found. Your app is lean and mean.');
            return Command::SUCCESS;
        }

        $this->warn("Found {$ghosts->count()} potentially dead routes:");
        $this->table(
            ['Route Name / URI', 'Methods', 'Last Used', 'Hits'],
            $ghosts->map(fn ($route) => [
                $route['name'],
                implode('|', $route['methods']),
                $route['last_used_at']?->diffForHumans() ?? 'Never',
                $route['hits'],
            ])
        );

        return Command::SUCCESS;
    }

    /**
     * Determine if the tracking middleware runs for the given route.
     */
    protected function isTracked(Router $router, RoutingRoute $route): bool
    {
        foreach ($router->gatherRouteMiddleware($route) as $middleware) {
            if ($middleware === TrackRouteUsage::class) {
                return true;
            }
        }

        return false;
    }
}
