<?php

declare(strict_types=1);

namespace App\Domain\Feed\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Trop de vidéos envoyées sur 24 heures glissantes (`xomiton.feed.video_per_day`, Q96) : 429 de code stable
 * `too_many_requests`, `Retry-After` en secondes (instant où la plus ancienne sort de la fenêtre) et `retry_after` dans le corps.
 */
final class VideoQuotaExceeded extends DomainException
{
    public function __construct(private readonly int $max, private readonly int $retryAfter)
    {
        parent::__construct('feed.video.quota', ['max' => $max]);
    }

    public function errorCode(): string
    {
        return 'too_many_requests';
    }

    public function status(): int
    {
        return 429;
    }

    public function extra(): array
    {
        return ['retry_after' => $this->retryAfter];
    }

    public function headers(): array
    {
        return ['Retry-After' => (string) $this->retryAfter];
    }
}
