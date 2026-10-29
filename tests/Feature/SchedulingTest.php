<?php

namespace Jarryd\GhostBuster\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use InvalidArgumentException;
use Jarryd\GhostBuster\Tests\TestCase;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;

class SchedulingTest extends TestCase
{
    #[Test]
    #[DefineEnvironment('useCacheDriver')]
    public function it_schedules_sync_for_the_cache_driver()
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command, 'ghost:sync'));

        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events->first()->expression);
    }

    #[Test]
    public function it_does_not_schedule_sync_for_the_database_driver()
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command, 'ghost:sync'));

        $this->assertCount(0, $events);
    }

    #[Test]
    #[DefineEnvironment('useInvalidFrequency')]
    public function it_rejects_an_invalid_sync_frequency()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ghost-buster.sync_frequency [whenever]');

        $this->app->make(Schedule::class);
    }

    protected function useCacheDriver($app): void
    {
        $app['config']->set('ghost-buster.driver', 'cache');
    }

    protected function useInvalidFrequency($app): void
    {
        $app['config']->set('ghost-buster.driver', 'cache');
        $app['config']->set('ghost-buster.sync_frequency', 'whenever');
    }
}
