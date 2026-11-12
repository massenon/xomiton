<?php

declare(strict_types=1);

namespace App\Domain\Community\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Réponse validée refusée (P18-03) : la réponse n'appartient pas au sujet, n'est plus publiée (masquée, supprimée) ou
 * le sujet lui-même n'est pas publié.
 */
final class ReplyNotAcceptable extends DomainException
{
    public function __construct()
    {
        parent::__construct('community.forums.errors.not_acceptable');
    }

    public function errorCode(): string
    {
        return 'reply_not_acceptable';
    }
}
