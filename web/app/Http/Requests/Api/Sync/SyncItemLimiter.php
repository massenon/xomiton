<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Sync;

use App\Application\Shared\SosBurstWatch;
use App\Application\Sync\Push\ItemLimiter;
use App\Domain\Sync\SyncItemType;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Limiteurs nommés d'AppServiceProvider appliqués à chaque élément d'un lot `sync/push` (P9-06), sur la requête du lot
 * (même compte, même IP) et avec la clé que pose ThrottleRequests (`md5(nom.clé)`) : le quota est partagé avec la route
 * dédiée. Collecte : aucun limiteur propre (`pair/cases` n'a que `throttle:api`), le plafond `sync_push` des lots
 * s'applique.
 */
final class SyncItemLimiter implements ItemLimiter
{
    /** @var array<string, string> type d'élément → limiteur nommé */
    private const LIMITERS = [
        'message' => 'messages_write',
        'signalement' => 'report',
    ];

    public function __construct(
        private readonly Request $request,
        private readonly SosBurstWatch $sosWatch,
    ) {}

    public function attempt(SyncItemType $type): bool
    {
        $name = self::LIMITERS[$type->value] ?? null;
        $limiter = $name === null ? null : RateLimiter::limiter($name);

        if ($name === null || $limiter === null) {
            return true;
        }

        $limits = array_filter(
            Arr::wrap($limiter($this->request)),
            static fn (mixed $limit): bool => $limit instanceof Limit && ! $limit instanceof Unlimited,
        );

        foreach ($limits as $limit) {
            if (RateLimiter::tooManyAttempts(md5($name.$limit->key), $limit->maxAttempts)) {
                return false;
            }
        }

        foreach ($limits as $limit) {
            RateLimiter::hit(md5($name.$limit->key), $limit->decaySeconds);
        }

        return true;
    }

    public function sosReceived(): void
    {
        $this->sosWatch->record($this->request);
    }
}
