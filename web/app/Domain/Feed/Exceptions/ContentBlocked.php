<?php

declare(strict_types=1);

namespace App\Domain\Feed\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Texte refusé par le filtre de mots (`bad_words` « bloquer », FR-SOC-06) : message neutre, sans jamais citer le terme
 * ni exposer la liste (simulateur `post` : « un commentaire bloqué renvoie un message neutre »).
 */
final class ContentBlocked extends DomainException
{
    public function __construct(private readonly string $field = 'body')
    {
        parent::__construct('feed.api.content_blocked');
    }

    public function errorCode(): string
    {
        return 'content_blocked';
    }

    public function errors(): array
    {
        return [$this->field => [$this->userMessage()]];
    }
}
