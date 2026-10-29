<?php

namespace Jarryd\GhostBuster\Tests\Unit\Support;

use Jarryd\GhostBuster\Support\IgnoreMatcher;
use PHPUnit\Framework\Attributes\Test;
use Jarryd\GhostBuster\Tests\TestCase;
use Illuminate\Routing\Route;

class IgnoreMatcherTest extends TestCase
{
    #[Test]
    public function it_matches_name_patterns()
    {
        config(['ghost-buster.ignore_patterns' => ['name:telescope*']]);

        $this->assertTrue(IgnoreMatcher::shouldIgnore('telescope.index', 'name'));
        $this->assertFalse(IgnoreMatcher::shouldIgnore('other.route', 'name'));
    }

    #[Test]
    public function it_matches_uri_patterns()
    {
        config(['ghost-buster.ignore_patterns' => ['uri:test/*']]);

        $this->assertTrue(IgnoreMatcher::shouldIgnore('test/ignore-me', 'uri'));
        $this->assertFalse(IgnoreMatcher::shouldIgnore('api/test', 'uri'));
    }

    #[Test]
    public function it_matches_auto_patterns()
    {
        config(['ghost-buster.ignore_patterns' => ['storage.local']]);

        // Should match both name and uri
        $this->assertTrue(IgnoreMatcher::shouldIgnore('storage.local', 'name'));
        $this->assertTrue(IgnoreMatcher::shouldIgnore('storage.local', 'uri'));
        $this->assertFalse(IgnoreMatcher::shouldIgnore('other.route', 'name'));
    }

    #[Test]
    public function it_matches_route_objects_directly()
    {
        config(['ghost-buster.ignore_patterns' => [
            'name:telescope*',
            'uri:test/*',
            'storage.local',
        ]]);

        $route1 = (new Route(['GET'], '/telescope/dashboard', fn() => null))->name('telescope.dashboard');
        $route2 = (new Route(['GET'], '/test/ignore-me', fn() => null))->name('test.ignore');
        $route3 = (new Route(['GET'], '/storage/local', fn() => null))->name('storage.local');
        $route4 = (new Route(['GET'], '/keep-me', fn() => null))->name('keep.route');

        $this->assertTrue(IgnoreMatcher::route($route1));
        $this->assertTrue(IgnoreMatcher::route($route2));
        $this->assertTrue(IgnoreMatcher::route($route3));
        $this->assertFalse(IgnoreMatcher::route($route4));
    }

    #[Test]
    public function it_respects_wildcards()
    {
        config(['ghost-buster.ignore_patterns' => ['name:foo*', 'uri:bar/*']]);

        $this->assertTrue(IgnoreMatcher::shouldIgnore('foo.index', 'name'));
        $this->assertTrue(IgnoreMatcher::shouldIgnore('bar/baz', 'uri'));
        $this->assertFalse(IgnoreMatcher::shouldIgnore('baz.foo', 'name'));
    }
}
