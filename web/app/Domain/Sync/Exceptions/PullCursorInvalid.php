<?php

declare(strict_types=1);

namespace App\Domain\Sync\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * `GET sync/pull?since=` illisible (jeton modifié, tronqué ou d'une autre version) : 422 `cursor_invalid`.
 * L'application relance un tirage complet (sans `since`).
 */
final class PullCursorInvalid extends DomainException
{
    public function __construct()
    {
        parent::__construct('sync.cursor_invalid');
    }

    public function errorCode(): string
    {
        return 'cursor_invalid';
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return ['since' => [$this->userMessage()]];
    }
}
