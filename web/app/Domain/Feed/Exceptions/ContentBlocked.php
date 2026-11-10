<?php

declare(strict_types=1);

namespace App\Domain\Feed\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Texte refusé par le filtre de contenus (`bad_words` « bloquer », motifs de coordonnées, FR-SOC-06, D-IOS-9) : 422 de
 * code stable `content_rejected`, message neutre et bienveillant, sans jamais citer le terme ni exposer la liste. Le
 * code était `content_blocked` avant le lot IOS-10 (aucun client ne le lisait).
 */
final class ContentBlocked extends DomainException
{
    public function __construct(private readonly string $field = 'body')
    {
        parent::__construct('feed.api.content_blocked');
    }

    public function errorCode(): string
    {
        return 'content_rejected';
    }

    public function errors(): array
    {
        return [$this->field => [$this->userMessage()]];
    }
}
