<?php

namespace Jarryd\GhostBuster\Tests\Feature;

use Jarryd\GhostBuster\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;

class DriverTrackingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_uses_database_driver_by_default()
    {
        config(['ghost-buster.driver' => 'database']);

        Route::get('/db-route', function () {
            return response('OK', 200);
        })->name('db.route')->middleware('web');

        $this->get('/db-route');

        $this->assertDatabaseHas('route_usage', [
            'route_name' => 'db.route',
            'method' => 'GET',
        ]);
    }

    #[Test]
    public function it_uses_cache_driver_when_configured()
    {
        config(['ghost-buster.driver' => 'cache']);

        Route::get('/cache-route', function () {
            return response('OK', 200);
        })->name('cache.route')->middleware('web');

        $this->get('/cache-route');

        $this->assertDatabaseCount('route_usage', 0);

        $this->assertTrue(Cache::has('ghost_buster:hit:GET:cache.route'));
    }

    #[Test]
    public function it_syncs_from_cache_to_database_correctly()
    {
        config([
            'ghost-buster.driver' => 'cache',
            'ghost-buster.cache_prefix' => 'ghost_buster:hit:',
        ]);

        Route::get('/sync-route', function () {
            return response('OK');
        })->name('sync.route')->middleware('web');

        $timestamp = now()->timestamp;

        Cache::forever(
            'ghost_buster:hit:GET:sync.route',
            $timestamp
        );

        $this->artisan('ghost:sync')
            ->expectsOutput('Syncing route usage from cache...')
            ->expectsOutput('Synced 1 route hits to the database.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('route_usage', [
            'route_name' => 'sync.route',
            'method' => 'GET',
        ]);

        $this->assertFalse(
            Cache::has('ghost_buster:hit:GET:sync.route')
        );
    }
}
