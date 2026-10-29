<?php

namespace Jarryd\GhostBuster\Support;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class UsageRecorder
{
    /**
     * Upsert a usage row, bumping last_used_at and adding to the hit count.
     */
    public static function record(string $method, string $routeName, DateTimeInterface $lastUsedAt, int $hits = 1): void
    {
        $query = DB::table('route_usage');
        $existingHits = $query->getGrammar()->wrap('route_usage.hits');

        $query->upsert([
            'route_name' => $routeName,
            'method' => $method,
            'hits' => $hits,
            'last_used_at' => $lastUsedAt,
        ], ['route_name', 'method'], [
            'last_used_at',
            'hits' => DB::raw("{$existingHits} + " . (int) $hits),
        ]);
    }
}
