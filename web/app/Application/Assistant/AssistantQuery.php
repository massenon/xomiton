<?php

declare(strict_types=1);

namespace App\Application\Assistant;

use App\Models\AssistantConversation;
use App\Models\User;

/**
 * Question d'un·e membre transmise au fournisseur (AssistantProvider, P8-11) : jamais une question de crise ni celle
 * d'un·e visiteur·se (AskAssistant les traite avant). `conversation` : conversation enregistrée de la personne, à
 * poursuivre si `persist` est vrai (historique lisible par un pilote LLM) ; null pour un échange non enregistré.
 */
final readonly class AssistantQuery
{
    public function __construct(
        public string $question,
        public User $user,
        public bool $persist = false,
        public ?AssistantConversation $conversation = null,
    ) {}
}
