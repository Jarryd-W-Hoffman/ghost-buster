<?php

namespace Jarryd\GhostBuster\Tests\Feature;

use Jarryd\GhostBuster\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

class GhostBusterCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_does_not_list_ignored_routes_as_ghosts()
    {
        // Configure ignore patterns with typed examples
        config([
            'ghost-buster.ignore_patterns' => [
                'name:ignored.route',
                'uri:/ignored/*',
                'storage.local', // auto pattern
            ],
        ]);

        $threshold = now()->subDays(30);

        // Routes that should be ignored
        Route::get('/ignored/uri', fn() => 'OK')->name('ignored.uri')->middleware('web');
        Route::get('/ignored-route', fn() => 'OK')->name('ignored.route')->middleware('web');
        Route::get('/storage/local', fn() => 'OK')->name('storage.local')->middleware('web');

        // Route that should be tracked
        Route::get('/tracked-route', fn() => 'OK')->name('tracked.route')->middleware('web');

        // Hit only the tracked route
        $this->get('/tracked-route');

        // Manually insert usage for tracked route older than threshold for testing
        DB::table('route_usage')->updateOrInsert(
    ['route_name' => 'tracked.route', 'method' => 'GET'],
    ['last_used_at' => now()->subDays(31)]
);


        // Run the ghost:bust command
        $command = $this->artisan('ghost:bust', ['--days' => 30]);

        $command->expectsOutput('Checking for routes unused in the last 30 days...');
        $command->expectsTable(
            ['Route Name / URI', 'Methods'],
            [
                ['tracked.route', 'GET|HEAD'],
            ]
        );

        $command->assertExitCode(0);
    }

    #[Test]
    public function it_lists_all_non_ignored_routes_as_ghosts()
    {
        config(['ghost-buster.ignore_patterns' => ['name:ignored.*']]);

        Route::get('/tracked', fn() => 'OK')->name('tracked.route')->middleware('web');
        Route::get('/ignored', fn() => 'OK')->name('ignored.route')->middleware('web');

        // Do not hit any routes, so everything is potentially dead
        $this->artisan('ghost:bust', ['--days' => 0])
            ->expectsOutput('Checking for routes unused in the last 0 days...')
            ->expectsTable(
                ['Route Name / URI', 'Methods'],
                [
                    ['tracked.route', 'GET|HEAD'],
                ]
            )
            ->assertExitCode(0);
    }
}
