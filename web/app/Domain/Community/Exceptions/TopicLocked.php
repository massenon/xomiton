<?php

declare(strict_types=1);

namespace App\Domain\Community\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Réponse refusée : le sujet est fermé par la modération (`forum_topics.is_locked`) ou son forum l'est (`forums.is_active`
 * faux). Il reste lisible.
 */
final class TopicLocked extends DomainException
{
    public function __construct()
    {
        parent::__construct('community.forums.errors.locked');
    }

    public function errorCode(): string
    {
        return 'topic_locked';
    }
}
