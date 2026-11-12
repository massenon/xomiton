<?php

declare(strict_types=1);

namespace App\Domain\Content\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Conversation en BD d'un·e membre refusée (P20-07, P20-08) : contenu refusé par les filtres (`bd_content_rejected` :
 * mot interdit, numéro de téléphone, adresse e-mail, lien), grammaire non respectée à la proposition (`bd_invalid`),
 * transition impossible (`bd_not_editable`, `bd_not_submittable`, `bd_not_published`, `bd_not_pending`…). 422 de code
 * stable ; `errors` par champ (`title`, `panels.0.bubbles.1.texte`…) et `issues` (format de ConversationGrammar) pour
 * l'éditeur. Message en clair pour la personne, sans jamais citer le terme filtré.
 */
final class BdConversationRefused extends DomainException
{
    /**
     * @param  array<string, list<string>>  $fieldErrors
     * @param  list<array{level: string, code: string, panel: int|null, bubble: int|null, message: string}>  $issues
     * @param  array<string, mixed>  $replace
     */
    public function __construct(
        private readonly string $reason,
        private readonly array $fieldErrors = [],
        private readonly array $issues = [],
        array $replace = [],
    ) {
        parent::__construct('conversations_bd.errors.'.$reason, $replace);
    }

    public function errorCode(): string
    {
        return $this->reason;
    }

    public function errors(): array
    {
        return $this->fieldErrors;
    }

    public function extra(): array
    {
        return $this->issues === [] ? [] : ['issues' => $this->issues];
    }

    /** @return list<array{level: string, code: string, panel: int|null, bubble: int|null, message: string}> */
    public function issues(): array
    {
        return $this->issues;
    }
}
