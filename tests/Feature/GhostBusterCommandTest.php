<?php

namespace Jarryd\GhostBuster\Tests\Feature;

use Jarryd\GhostBuster\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\Attributes\DefineEnvironment;

class GhostBusterCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_does_not_list_ignored_routes_as_ghosts()
    {
        config([
            'ghost-buster.ignore_patterns' => [
                'name:ignored.route',
                'uri:ignored/*',
                'name:storage.*',
            ],
        ]);

        Route::get('/ignored/uri', fn() => 'OK')->name('ignored.uri')->middleware('web');
        Route::get('/ignored-route', fn() => 'OK')->name('ignored.route')->middleware('web');
        Route::get('/storage/local', fn() => 'OK')->name('storage.local')->middleware('web');
        Route::get('/tracked-route', fn() => 'OK')->name('tracked.route')->middleware('web');

        $this->get('/tracked-route');

        DB::table('route_usage')->updateOrInsert(
            ['route_name' => 'tracked.route', 'method' => 'GET'],
            ['last_used_at' => now()->subDays(31)]
        );

        $this->artisan('ghost:bust', ['--days' => 30])
            ->expectsOutput('Checking for routes unused in the last 30 days...')
            ->expectsOutputToContain('tracked.route')
            ->doesntExpectOutputToContain('ignored.route')
            ->doesntExpectOutputToContain('ignored.uri')
            ->doesntExpectOutputToContain('storage.local')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_lists_all_non_ignored_routes_as_ghosts()
    {
        config(['ghost-buster.ignore_patterns' => ['name:ignored.*']]);

        Route::get('/tracked', fn() => 'OK')->name('tracked.route')->middleware('web');
        Route::get('/ignored', fn() => 'OK')->name('ignored.route')->middleware('web');

        $this->artisan('ghost:bust', ['--days' => 0])
            ->expectsOutput('Checking for routes unused in the last 0 days...')
            ->expectsOutputToContain('tracked.route')
            ->doesntExpectOutputToContain('ignored.route')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_tracks_unnamed_routes_per_method()
    {
        config(['ghost-buster.ignore_patterns' => ['name:storage.*']]);

        Route::get('/widgets', fn () => 'OK')->middleware('web');
        Route::post('/widgets', fn () => 'OK')->middleware('web');

        $this->get('/widgets');

        $this->artisan('ghost:bust')
            ->expectsTable(['Route Name / URI', 'Methods', 'Last Used', 'Hits'], [['widgets', 'POST', 'Never', 0]])
            ->assertExitCode(0);
    }

    #[Test]
    public function it_shows_last_used_and_hits_for_stale_routes()
    {
        config(['ghost-buster.ignore_patterns' => ['name:storage.*']]);

        Route::get('/stale', fn () => 'OK')->name('stale.route')->middleware('web');

        DB::table('route_usage')->insert([
            'route_name' => 'stale.route',
            'method' => 'GET',
            'hits' => 7,
            'last_used_at' => now()->subDays(40),
        ]);

        $this->artisan('ghost:bust')
            ->expectsTable(['Route Name / URI', 'Methods', 'Last Used', 'Hits'], [['stale.route', 'GET|HEAD', '1 month ago', 7]])
            ->assertExitCode(0);
    }

    #[Test]
    public function it_rejects_invalid_days()
    {
        $this->artisan('ghost:bust', ['--days' => -5])
            ->expectsOutput('The --days option must be a whole number of 0 or more.')
            ->assertExitCode(2);

        $this->artisan('ghost:bust', ['--days' => 'abc'])->assertExitCode(2);
        $this->artisan('ghost:bust', ['--days' => '1.5'])->assertExitCode(2);
    }

    #[Test]
    public function it_applies_except_patterns_for_a_single_run()
    {
        config(['ghost-buster.ignore_patterns' => ['name:storage.*']]);

        Route::get('/admin/users', fn () => 'OK')->name('admin.users')->middleware('web');
        Route::get('/kept', fn () => 'OK')->name('kept.route')->middleware('web');

        $this->artisan('ghost:bust', ['--except' => ['name:admin.*']])
            ->expectsOutputToContain('kept.route')
            ->doesntExpectOutputToContain('admin.users')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_outputs_json()
    {
        config(['ghost-buster.ignore_patterns' => ['name:storage.*']]);

        Route::get('/used', fn () => 'OK')->name('used.route')->middleware('web');
        Route::post('/unused', fn () => 'OK')->name('unused.route')->middleware('web');

        $this->get('/used');

        Artisan::call('ghost:bust', ['--json' => true]);

        $this->assertSame([
            ['name' => 'unused.route', 'methods' => ['POST'], 'last_used_at' => null, 'hits' => 0],
        ], json_decode(Artisan::output(), true));
    }

    #[Test]
    public function it_skips_routes_without_tracking_middleware()
    {
        config(['ghost-buster.ignore_patterns' => ['name:storage.*']]);

        Route::get('/untracked', fn () => 'OK')->name('untracked.route');
        Route::get('/tracked', fn () => 'OK')->name('tracked.route')->middleware('web');

        $this->artisan('ghost:bust')
            ->expectsOutputToContain('Skipped 1 routes that are not covered by the tracking middleware')
            ->expectsOutputToContain('tracked.route')
            ->doesntExpectOutputToContain('untracked.route')
            ->assertExitCode(0);
    }

    #[Test]
    #[DefineEnvironment('trackAllRoutes')]
    public function it_tracks_and_reports_every_route_when_tracking_globally()
    {
        config(['ghost-buster.ignore_patterns' => ['name:storage.*']]);

        Route::get('/no-group', fn () => 'OK')->name('no.group');
        Route::get('/no-group-unused', fn () => 'OK')->name('no.group.unused');

        $this->get('/no-group');

        $this->assertDatabaseHas('route_usage', ['route_name' => 'no.group', 'hits' => 1]);

        $this->artisan('ghost:bust')
            ->expectsTable(['Route Name / URI', 'Methods', 'Last Used', 'Hits'], [['no.group.unused', 'GET|HEAD', 'Never', 0]])
            ->assertExitCode(0);
    }

    protected function trackAllRoutes($app): void
    {
        $app['config']->set('ghost-buster.track_all_routes', true);
    }
}
