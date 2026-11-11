<?php

declare(strict_types=1);

namespace App\Application\Sync\Pull;

use Carbon\CarbonImmutable;

/**
 * Résultat de PullChanges : page de chaque collection servie, jeton de reprise, reste-t-il des pages, borne haute
 * du tirage.
 */
final readonly class PullResult
{
    /**
     * @param  array<string, array{reset: bool, upserts: list<array<string, mixed>>, deletes: list<string|int>, has_more: bool}>  $collections
     */
    public function __construct(
        public array $collections,
        public PullCursor $cursor,
        public bool $hasMore,
        public CarbonImmutable $until,
    ) {}
}
