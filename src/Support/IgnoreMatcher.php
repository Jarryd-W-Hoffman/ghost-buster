<?php

namespace Jarryd\GhostBuster\Support;

use Illuminate\Support\Str;
use Illuminate\Routing\Route;

class IgnoreMatcher
{
    /**
     * Determine if the value matches an ignore pattern. Patterns default to
     * the configured ignore_patterns, with any extra patterns appended.
     */
    public static function shouldIgnore(string $value, ?string $type = null, array $extraPatterns = []): bool
    {
        $patterns = array_merge(config('ghost-buster.ignore_patterns', []), $extraPatterns);

        foreach ($patterns as $pattern) {
            [$patternType, $patternValue] = self::parsePattern($pattern);

            if ($patternType && $type && $patternType !== $type) {
                continue;
            }

            $candidate = $value;

            if (($patternType ?? $type) === 'uri') {
                $patternValue = self::normalizeUri($patternValue);
                $candidate = self::normalizeUri($value);
            }

            if (Str::is($patternValue, $candidate)) {
                return true;
            }
        }

        return false;
    }

    public static function route(Route $route, array $extraPatterns = []): bool
    {
        $name = $route->getName();

        return
            ($name !== null && self::shouldIgnore($name, 'name', $extraPatterns)) ||
            self::shouldIgnore($route->uri(), 'uri', $extraPatterns);
    }

    protected static function parsePattern(string $pattern): array
    {
        $parts = explode(':', $pattern, 2);

        if (count($parts) === 2 && in_array($parts[0], ['name', 'uri'], true)) {
            return $parts;
        }

        return [null, $pattern];
    }

    /**
     * Laravel stores route URIs without a leading slash (except "/"),
     * so "uri:/up" and "uri:up" should behave the same.
     */
    protected static function normalizeUri(string $uri): string
    {
        return ltrim($uri, '/') ?: '/';
    }
}
