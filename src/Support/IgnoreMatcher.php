<?php

namespace Jarryd\GhostBuster\Support;

use Illuminate\Support\Str;
use Illuminate\Routing\Route;

class IgnoreMatcher
{
    public static function shouldIgnore(string $value, ?string $type = null): bool
    {
        foreach (config('ghost-buster.ignore_patterns', []) as $pattern) {
            [$patternType, $patternValue] = self::parsePattern($pattern);

            if ($patternType && $type && $patternType !== $type) {
                continue;
            }

            if (Str::is($patternValue, $value)) {
                return true;
            }
        }

        return false;
    }

    public static function route(Route $route): bool
    {
        return
            self::shouldIgnore($route->getName() ?? '', 'name') ||
            self::shouldIgnore($route->uri(), 'uri');
    }

    protected static function parsePattern(string $pattern): array
    {
        if (str_contains($pattern, ':')) {
            return explode(':', $pattern, 2);
        }

        return [null, $pattern];
    }
}
