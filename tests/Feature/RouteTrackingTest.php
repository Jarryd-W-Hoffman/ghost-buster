<?php

namespace Jarryd\GhostBuster\Tests\Feature;

use Jarryd\GhostBuster\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Route;

class RouteTrackingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_records_route_usage_when_a_route_is_visited()
    {
        Route::get('/test-route', function () {
            return response('OK', 200);
        })->name('test.route')->middleware('web');

        $this->get('/test-route');

        $this->assertDatabaseHas('route_usage', [
            'route_name' => 'test.route',
            'method' => 'GET',
        ]);
    }

    #[Test]
    public function it_does_not_record_failed_requests()
    {
        Route::get('/fail-route', function () {
            return response('Error', 500);
        })->name('fail.route')->middleware('web');

        $this->get('/fail-route');

        $this->assertDatabaseMissing('route_usage', [
            'route_name' => 'fail.route',
        ]);
    }

    #[Test]
    public function it_ignores_routes_configured_to_be_ignored()
    {
        config(['ghost-buster.ignore_patterns' => ['ignored.route']]);

        Route::get('/ignored-route', function () {
            return response('OK', 200);
        })->name('ignored.route')->middleware('web');

        $this->get('/ignored-route');

        $this->assertDatabaseMissing('route_usage', [
            'route_name' => 'ignored.route',
        ]);
    }

    #[Test]
    public function it_ignores_named_routes_matching_a_uri_pattern()
    {
        config(['ghost-buster.ignore_patterns' => ['uri:/health']]);

        Route::get('/health', fn () => 'OK')->name('health.check')->middleware('web');

        $this->get('/health');

        $this->assertDatabaseMissing('route_usage', [
            'route_name' => 'health.check',
        ]);
    }

    #[Test]
    public function it_loads_default_config_without_publishing()
    {
        $this->assertSame('database', config('ghost-buster.driver'));
        $this->assertContains('name:telescope*', config('ghost-buster.ignore_patterns'));
    }

    #[Test]
    public function it_counts_hits()
    {
        Route::get('/counted', fn () => 'OK')->name('counted.route')->middleware('web');

        $this->get('/counted');
        $this->get('/counted');
        $this->get('/counted');

        $this->assertDatabaseHas('route_usage', ['route_name' => 'counted.route', 'hits' => 3]);
    }

    #[Test]
    public function it_throttles_database_writes_when_configured()
    {
        config(['ghost-buster.throttle' => 60]);

        Route::get('/throttled', fn () => 'OK')->name('throttled.route')->middleware('web');

        $this->get('/throttled');
        $this->get('/throttled');

        $this->assertDatabaseHas('route_usage', ['route_name' => 'throttled.route', 'hits' => 1]);

        $this->travel(61)->seconds();
        $this->get('/throttled');

        $this->assertDatabaseHas('route_usage', ['route_name' => 'throttled.route', 'hits' => 2]);
    }
}
