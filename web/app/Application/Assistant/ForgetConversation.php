<?php

declare(strict_types=1);

namespace App\Application\Assistant;

use App\Models\User;

/**
 * « Effacer la conversation » (P8-11, prototype assistant.php l.36) pour un échange enregistré : la conversation de la
 * personne et ses messages (suppression en cascade) disparaissent, marquée pour relecture ou non (droit d'effacement,
 * minimisation). Une alerte déjà créée par « Parler à un humain » n'est pas touchée : elle ne contient pas la
 * conversation (EscalateToHuman). Conversation d'un autre compte : 404, sans oracle.
 */
final class ForgetConversation
{
    public function __invoke(User $user, int $conversationId): void
    {
        AskAssistant::conversationOf($user, $conversationId)->delete();
    }
}
