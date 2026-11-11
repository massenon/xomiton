<?php

declare(strict_types=1);

namespace App\Domain\Moderation\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Refus métier d'une décision de modération (P12-06) : signalement déjà traité, cible administratrice, auteur·e
 * introuvable, contenu non masquable, message non lisible… Message de lang/fr/admin.php (`moderation.errors.<raison>`), 422.
 */
final class ModerationRefused extends DomainException
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public function __construct(private readonly string $reason, array $replace = [])
    {
        parent::__construct('admin.moderation.errors.'.$reason, $replace);
    }

    public function errorCode(): string
    {
        return $this->reason;
    }
}
