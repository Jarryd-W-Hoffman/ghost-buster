<?php

namespace Jarryd\GhostBuster\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Jarryd\GhostBuster\Support\IgnoreMatcher;
use Jarryd\GhostBuster\Support\UsageRecorder;

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
            if (IgnoreMatcher::route($route)) {
                continue;
            }

            $routeName = $route->getName() ?? $route->uri();

            foreach ($route->methods() as $method) {
                $cacheKey = "{$prefix}{$method}:{$routeName}";

                // pull() reads and removes in one step so hits recorded mid-sync aren't lost
                if ($timestamp = Cache::pull($cacheKey)) {
                    $hits = (int) Cache::pull("{$prefix}count:{$method}:{$routeName}", 1);

                    UsageRecorder::record($method, $routeName, Carbon::createFromTimestamp($timestamp, config('app.timezone')), $hits);
                    $syncedCount++;
                }
            }
        }

        $this->info("Synced {$syncedCount} route hits to the database.");
        return Command::SUCCESS;
    }
}
