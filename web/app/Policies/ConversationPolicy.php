<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

/**
 * Messagerie : seul·e un·e participant·e encore présent·e lit et écrit ; la gestion (ajouter, retirer, renommer)
 * revient au créateur ou aux participant·es de rôle admin. Aucune permission d'administration n'ouvre une conversation privée.
 */
final class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('social.access');
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $this->participant($user, $conversation) !== null;
    }

    public function create(User $user): bool
    {
        return $user->can('social.access');
    }

    public function sendMessage(User $user, Conversation $conversation): bool
    {
        return $user->can('social.access') && $this->view($user, $conversation);
    }

    public function leave(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function manage(User $user, Conversation $conversation): bool
    {
        if ($conversation->created_by !== null && (int) $conversation->created_by === (int) $user->getKey()) {
            return $this->view($user, $conversation);
        }

        return $this->participant($user, $conversation)?->role === ConversationParticipant::ROLE_ADMIN;
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $this->manage($user, $conversation);
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return $this->manage($user, $conversation);
    }

    private function participant(User $user, Conversation $conversation): ?ConversationParticipant
    {
        return ConversationParticipant::query()
            ->where('conversation_id', $conversation->getKey())
            ->where('user_id', $user->getKey())
            ->whereNull('left_at')
            ->first();
    }
}
