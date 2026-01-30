<?php

namespace Jarryd\GhostBuster;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Jarryd\GhostBuster\Commands\GhostBusterCommand;
use Jarryd\GhostBuster\Commands\SyncRouteUsage;
use Jarryd\GhostBuster\Http\Middleware\TrackRouteUsage;
use Illuminate\Console\Scheduling\Schedule;

class GhostBusterServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(Router $router): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GhostBusterCommand::class,
                SyncRouteUsage::class,
            ]);

            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

            $this->publishes([
                __DIR__ . '/../config/ghost-buster.php' => config_path('ghost-buster.php'),
            ], 'ghost-buster-config');
        }

        $groups = config('ghost-buster.middleware_groups', ['web', 'api']);

        if (is_array($groups)) {
            foreach ($groups as $group) {
                $router->pushMiddlewareToGroup($group, TrackRouteUsage::class);
            }
        }

        if (config('ghost-buster.driver') === 'cache') {
            $callback = function () {
                $schedule = $this->app->make(Schedule::class);
                $frequency = config('ghost-buster.sync_frequency', 'hourly');

                $schedule->command('ghost:sync')->$frequency();
            };

            $this->app->isBooted() ? $callback() : $this->app->booted($callback);
        }
    }
}
