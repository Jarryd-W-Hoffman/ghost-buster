<?php

namespace Jarryd\GhostBuster\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Jarryd\GhostBuster\Support\IgnoreMatcher;

class SyncRouteUsage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghost:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flush route hits from cache to the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if(config('ghost-buster.driver') !== 'cache') {
            $this->error('Cache driver not enabled. Nothing to sync.');
            return Command::SUCCESS;
        }

        $this->info('Syncing route usage from cache...');

        $prefix = config('ghost-buster.cache_prefix', 'ghost_buster:hit:');
        $routes = Route::getRoutes();
        $syncedCount = 0;

        foreach ($routes as $route) {
            $routeName = $route->getName() ?? $route->uri();

            if (IgnoreMatcher::shouldIgnore($routeName)) {
                continue;
            }

            foreach ($route->methods() as $method) {
                $cacheKey = "{$prefix}{$method}:{$routeName}";

                if ($timestamp = Cache::get($cacheKey)) {
                    DB::table('route_usage')->updateOrInsert([
                        'route_name' => $routeName,
                        'method' => $method,
                    ], [
                        'last_used_at' => date('Y-m-d H:i:s', $timestamp),
                    ]);

                    Cache::forget($cacheKey);
                    $syncedCount++;
                }
            }
        }

        $this->info("Synced {$syncedCount} route hits to the database.");
        return Command::SUCCESS;
    }
}
