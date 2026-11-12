<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Origine d'une conversation en BD (P20-03 ; `bd_conversations.origin`) : modèle de l'équipe (import de MODELES.md,
 * administration) ou création d'un·e membre (éditeur, lot B).
 */
enum BdConversationOrigin: string
{
    case Template = 'modele';
    case Member = 'membre';
}
