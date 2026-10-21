<?php

declare(strict_types=1);

namespace App\Application\Admin\Assistant;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Collection;

/**
 * Conversations signalées de l'assistant (P12-07, `assistant_conversations.is_flagged`) : un échange n'existe que si la
 * personne a demandé son enregistrement (historique non persistant par défaut, Q55). La liste ne montre ni pseudo ni texte ;
 * lire l'échange (corps chiffrés) est une action explicite, **journalisée** (`assistant.conversation_read`, sans texte) ;
 * « traitée » retire le signalement sans supprimer l'échange.
 */
final class ReviewAssistantConversations
{
    /**
     * @return Collection<int, AssistantMessage>
     */
    public function read(AssistantConversation $conversation, User $actor): Collection
    {
        Audit::log('assistant.conversation_read', $conversation, null, $actor);

        return $this->peek($conversation);
    }

    /**
     * Relecture dans la même fenêtre ouverte (aucune nouvelle ligne au journal).
     *
     * @return Collection<int, AssistantMessage>
     */
    public function peek(AssistantConversation $conversation): Collection
    {
        return $conversation->messages()->get(['id', 'conversation_id', 'role', 'body', 'created_at']);
    }

    public function resolve(AssistantConversation $conversation, User $actor): void
    {
        $conversation->forceFill(['is_flagged' => false, 'flag_reason' => null])->save();
        Audit::log('assistant.conversation_resolved', $conversation, null, $actor);
    }
}
