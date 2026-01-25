<?php

namespace Jarryd\GhostBuster\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Jarryd\GhostBuster\GhostBusterServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use Jarryd\GhostBuster\Http\Middleware\TrackRouteUsage;

class TestCase extends Orchestra
{
    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.key', 'base64:a45mLWt/IxBtjcI9/oSCeIOS/TnAcPYmI/4YdZV1MRE=');
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            GhostBusterServiceProvider::class,
        ];
    }

    /**
     * Set up the test environment.
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(Kernel::class)->pushMiddleware(
            TrackRouteUsage::class
        );
    }
}
