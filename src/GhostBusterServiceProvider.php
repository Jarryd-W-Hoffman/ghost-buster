<?php

namespace Jarryd\GhostBuster;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Jarryd\GhostBuster\Commands\GhostBusterCommand;
use Jarryd\GhostBuster\Commands\SyncRouteUsage;
use Jarryd\GhostBuster\Http\Middleware\TrackRouteUsage;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use InvalidArgumentException;

class GhostBusterServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ghost-buster.php', 'ghost-buster');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
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

        // Register via the HTTP kernel rather than the router: the kernel re-syncs
        // its groups onto the router whenever they change, which would wipe a
        // router-only registration.
        $this->callAfterResolving(HttpKernel::class, function ($kernel) {
            if (! $kernel instanceof Kernel) {
                return;
            }

            if (config('ghost-buster.track_all_routes', false)) {
                $kernel->pushMiddleware(TrackRouteUsage::class);
                return;
            }

            $defined = $kernel->getMiddlewareGroups();

            foreach ((array) config('ghost-buster.middleware_groups', ['web', 'api']) as $group) {
                if (isset($defined[$group])) {
                    $kernel->appendMiddlewareToGroup($group, TrackRouteUsage::class);
                }
            }
        });

        if (config('ghost-buster.driver') === 'cache') {
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
                $frequency = config('ghost-buster.sync_frequency', 'hourly');

                if (! is_string($frequency) || ! method_exists(Event::class, $frequency)) {
                    throw new InvalidArgumentException(
                        "Invalid ghost-buster.sync_frequency [{$frequency}]. Use a scheduler frequency method such as 'hourly' or 'daily'."
                    );
                }

                $schedule->command('ghost:sync')->$frequency();
            });
        }
    }
}
