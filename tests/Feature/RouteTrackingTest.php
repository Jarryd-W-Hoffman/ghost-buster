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
}
