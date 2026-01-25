<?php

namespace Jarryd\GhostBuster\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

class GhostBusterCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghost:bust {--days=30 : The number of days of inactivity before a route is considered unused}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Identify and list unused routes in the application based on the inactivity period.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $threshold = now()->subDays($days);

        $this->info("Checking for routes unused in the last {$days} days...");

        $allRoutes = collect(Route::getRoutes())->map(function ($route) {
            return [
                'name' => $route->getName() ?? $route->uri(),
                'method' => implode('|', $route->methods()),
            ];
        });

        $usedRoutes = DB::table('route_usage')
            ->where('last_used_at', '>=', $threshold)
            ->pluck('route_name')
            ->toArray();

        $ghosts = $allRoutes->reject(function ($route) use ($usedRoutes) {
            return in_array($route['name'], $usedRoutes);
        });

        if ($ghosts->isEmpty()) {
            $this->info('No ghosts found. Your app is lean and mean.');
            return Command::SUCCESS;
        }

        $this->warn("Found {$ghosts->count()} potentially dead routes:");
        $this->table(
            ['Route Name / URI', 'Methods'],
            $ghosts
        );

        return Command::SUCCESS;
    }
}
