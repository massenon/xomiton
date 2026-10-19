<?php

declare(strict_types=1);

namespace App\Policies;

use App\Application\Community\Events\EventAccess;
use App\Models\Event;
use App\Models\User;

/**
 * Événements (P8-04) : `social.access` lit les événements publiés qu'il ou elle peut voir (groupe privé : membres
 * actives, page : approuvée et active — App\Application\Community\Events\EventAccess), en crée et répond présent ;
 * l'organisateur·rice modifie ; `moderation.manage` modère et seule ouvre un événement aux visiteur·ses (`is_public`,
 * Q35 a : page publique indexée). Brouillons et événements masqués : organisateur·rice et modération seulement.
 */
final class EventPolicy
{
    public function __construct(private readonly EventAccess $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('social.access');
    }

    public function view(User $user, Event $event): bool
    {
        return $this->access->canView($user, $event);
    }

    public function create(User $user): bool
    {
        return $user->can('social.access');
    }

    /** Ouvrir un événement aux visiteur·ses (`is_public`) : modération seulement. */
    public function publishPublicly(User $user): bool
    {
        return $user->can('moderation.manage');
    }

    public function update(User $user, Event $event): bool
    {
        return $this->access->isOrganizer($user, $event) || $user->can('moderation.manage');
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->access->isOrganizer($user, $event) || $user->can('moderation.manage');
    }

    public function rsvp(User $user, Event $event): bool
    {
        return $user->can('social.access') && $event->isPublished() && $this->access->canView($user, $event);
    }
}
