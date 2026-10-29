<?php

namespace Jarryd\GhostBuster\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Jarryd\GhostBuster\GhostBusterServiceProvider;

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

        // Set DB_CONNECTION=mysql|pgsql (plus DB_HOST, DB_DATABASE, ...) to run
        // the suite against a real server; defaults to in-memory SQLite.
        if (in_array(env('DB_CONNECTION'), ['mysql', 'mariadb', 'pgsql'], true)) {
            $app['config']->set('database.default', env('DB_CONNECTION'));
            return;
        }

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
}
