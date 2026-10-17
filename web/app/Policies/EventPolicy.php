<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * Événements : `social.access` lit les événements publiés et répond présent ; l'organisateur·rice modifie ;
 * `moderation.manage` modère. Les brouillons ne sont visibles que de l'organisateur·rice et de la modération.
 */
final class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('social.access');
    }

    public function view(User $user, Event $event): bool
    {
        if ($user->can('moderation.manage') || $this->isOrganizer($user, $event)) {
            return true;
        }

        return $user->can('social.access') && $event->isPublished();
    }

    public function create(User $user): bool
    {
        return $user->can('social.access');
    }

    public function update(User $user, Event $event): bool
    {
        return $this->isOrganizer($user, $event) || $user->can('moderation.manage');
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->isOrganizer($user, $event) || $user->can('moderation.manage');
    }

    public function rsvp(User $user, Event $event): bool
    {
        return $user->can('social.access') && $event->isPublished();
    }

    private function isOrganizer(User $user, Event $event): bool
    {
        return $event->organizer_id !== null && (int) $event->organizer_id === (int) $user->getKey();
    }
}
