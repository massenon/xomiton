<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

/**
 * Messages : lisibles par les participant·es de la conversation ; l'auteur·rice supprime pour tout le monde,
 * la gestion de la conversation (créateur, admin) aussi. Le contenu chiffré n'est jamais ouvert à l'administration.
 */
final class MessagePolicy
{
    public function __construct(private readonly ConversationPolicy $conversations) {}

    public function view(User $user, Message $message): bool
    {
        return $message->conversation !== null && $this->conversations->view($user, $message->conversation);
    }

    public function create(User $user): bool
    {
        return $user->can('social.access');
    }

    public function update(User $user, Message $message): bool
    {
        return $this->isAuthor($user, $message) && $this->view($user, $message);
    }

    public function delete(User $user, Message $message): bool
    {
        if ($this->isAuthor($user, $message)) {
            return $this->view($user, $message);
        }

        return $message->conversation !== null && $this->conversations->manage($user, $message->conversation);
    }

    public function report(User $user, Message $message): bool
    {
        return ! $this->isAuthor($user, $message) && $this->view($user, $message);
    }

    private function isAuthor(User $user, Message $message): bool
    {
        return $message->user_id !== null && (int) $message->user_id === (int) $user->getKey();
    }
}
