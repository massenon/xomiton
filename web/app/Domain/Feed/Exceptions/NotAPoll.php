<?php

declare(strict_types=1);

namespace App\Domain\Feed\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/** `posts/{ulid}/poll/vote` sur une publication sans sondage : introuvable (404), comme une publication invisible. */
final class NotAPoll extends DomainException
{
    public function __construct()
    {
        parent::__construct('feed.errors.poll_not_found');
    }

    public function errorCode(): string
    {
        return 'not_found';
    }

    public function status(): int
    {
        return 404;
    }
}
