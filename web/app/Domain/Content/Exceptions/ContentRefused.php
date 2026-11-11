<?php

declare(strict_types=1);

namespace App\Domain\Content\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Refus métier d'une action d'administration du contenu (P12-03 à P12-08) : publication sans sous-titres, catégorie
 * encore utilisée, image Open Graph de mauvaise taille, réseau sans compte… Le message vient de
 * lang/fr/admin_content.php (`errors.<raison>`), 422.
 */
final class ContentRefused extends DomainException
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public function __construct(private readonly string $reason, array $replace = [])
    {
        parent::__construct('admin_content.errors.'.$reason, $replace);
    }

    public function errorCode(): string
    {
        return $this->reason;
    }
}
