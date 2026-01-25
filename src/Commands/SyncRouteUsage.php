<?php

namespace Jarryd\GhostBuster\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

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
        $this->info('Syncing route usage from cache...');

        $routes = Route::getRoutes();
        $syncedCount = 0;

        foreach ($routes as $route) {
            foreach ($route->methods() as $method) {
                $routeName = $route->getName() ?? $route->uri();
                $cacheKey = "ghost_buster:hit:{$method}:{$routeName}";

                if ($timestamp = Cache::get($cacheKey)) {
                    DB::table('route_usage')->updateOrInsert([
                        'route_name' => $routeName,
                        'method' => $method,
                    ], [
                        'last_used_at' => date('Y-m-d H:i:s', $timestamp),
                        'updated_at' => now(),
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
