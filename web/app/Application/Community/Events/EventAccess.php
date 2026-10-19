<?php

declare(strict_types=1);

namespace App\Application\Community\Events;

use App\Domain\Community\EventDisclosure;
use App\Enums\GroupPrivacy;
use App\Enums\MembershipStatus;
use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\GroupMember;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Database\Eloquent\Builder;

/**
 * Qui voit quel événement (P8-04, FR-SOC-05 ; ADR-29 : lectures Eloquent directes), en requête (listes) comme pour un
 * seul événement (EventPolicy::view) — les deux lisent les mêmes règles :
 *
 *  - membre (`social.access`) : événements publiés, sans groupe ou d'un groupe actif public — ou privé dont elle est
 *    propriétaire ou membre active —, sans page ou d'une page approuvée et active (ou la sienne) ; l'organisateur·rice
 *    et la modération voient en plus leurs brouillons et les événements masqués ;
 *  - blocage (FR-SOC-04, comme le fil et les forums) : un événement dont l'organisateur·rice et la personne qui lit se
 *    bloquent, dans un sens ou dans l'autre, n'existe pas pour elle (liste, fiche, « Je participe » : 404) ; la
 *    modération garde l'accès ;
 *  - visiteur·se : seulement les événements **publics** (Q35 a, EventDisclosure::isOpenToPublic) ;
 *  - adresse exacte et lien de connexion : EventDisclosure::revealsExactPlace (« Je participe », organisateur·rice,
 *    modération).
 */
final class EventAccess
{
    /**
     * Restreint la requête aux événements publiés qu'un·e membre peut voir.
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function forMember(Builder $query, User $viewer): Builder
    {
        $id = (int) $viewer->getKey();
        $memberOf = GroupMember::query()->select('group_id')->where('user_id', $id)->where('status', MembershipStatus::Active->value);

        return $query->published()
            ->where(static function (Builder $q) use ($id, $memberOf): void {
                $q->whereNull('events.group_id')
                    ->orWhereHas('group', static fn (Builder $group) => $group->active()->where(
                        static fn (Builder $g) => $g->where('privacy', GroupPrivacy::Public)->orWhere('owner_id', $id)->orWhereIn('groups.id', $memberOf),
                    ));
            })
            ->where(static function (Builder $q) use ($viewer): void {
                $q->whereNull('events.page_id')->orWhereHas('page', static fn (Builder $page) => $page->visibleTo($viewer));
            })
            ->where(static function (Builder $q) use ($id): void {
                $q->whereNull('events.organizer_id')->orWhere(static fn (Builder $r) => self::withoutBlocksWith($r, 'events.organizer_id', $id));
            });
    }

    /**
     * Restreint `$column` (identifiant de compte) aux comptes qui ne bloquent pas `$userId` et qu'il ou elle ne bloque
     * pas (listes d'événements, rappel de la veille, cloche « événement dans mon groupe »).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function withoutBlocksWith(Builder $query, string $column, int $userId): Builder
    {
        return $query->whereNotIn($column, UserBlock::query()->select('user_id')->where('blocked_user_id', $userId))
            ->whereNotIn($column, UserBlock::query()->select('blocked_user_id')->where('user_id', $userId));
    }

    /**
     * Restreint la requête aux événements ouverts aux visiteur·ses (page publique, plan du site).
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function forPublic(Builder $query): Builder
    {
        return $query->openToPublic()
            ->where(static function (Builder $q): void {
                $q->whereNull('events.group_id')->orWhereHas('group', static fn (Builder $group) => $group->active()->public());
            })
            ->where(static function (Builder $q): void {
                $q->whereNull('events.page_id')->orWhereHas('page', static fn (Builder $page) => $page->approved()->active());
            });
    }

    public function canView(User $viewer, Event $event): bool
    {
        if ($viewer->can('moderation.manage') || $this->isOrganizer($viewer, $event)) {
            return true;
        }

        return $viewer->can('social.access') && $event->isPublished() && $this->groupAllows($viewer, $event) && $this->pageAllows($viewer, $event)
            && ! $this->blockedWithOrganizer($viewer, $event);
    }

    public function isOpenToPublic(Event $event): bool
    {
        $group = $event->group_id !== null ? $event->group : null;
        $page = $event->page_id !== null ? $event->page : null;

        return EventDisclosure::isOpenToPublic(
            (bool) $event->is_public,
            $event->isPublished() && ! $event->trashed(),
            $event->group_id === null ? null : ($group !== null && $group->is_active && $group->privacy === GroupPrivacy::Public),
            $event->page_id === null ? null : ($page !== null && $page->isPubliclyVisible()),
        );
    }

    public function revealsExactPlace(User $viewer, Event $event, ?EventRsvp $rsvp): bool
    {
        return EventDisclosure::revealsExactPlace(
            $rsvp !== null && $rsvp->status === RsvpStatus::Going && (int) $rsvp->user_id === (int) $viewer->getKey(),
            $this->isOrganizer($viewer, $event),
            $viewer->can('moderation.manage'),
        );
    }

    public function isOrganizer(User $viewer, Event $event): bool
    {
        return $event->organizer_id !== null && (int) $event->organizer_id === (int) $viewer->getKey();
    }

    private function blockedWithOrganizer(User $viewer, Event $event): bool
    {
        return $event->organizer_id !== null
            && UserBlock::query()->between((int) $viewer->getKey(), (int) $event->organizer_id)->exists();
    }

    private function groupAllows(User $viewer, Event $event): bool
    {
        if ($event->group_id === null) {
            return true;
        }

        $group = $event->group;

        if ($group === null || ! $group->is_active) {
            return false;
        }

        if ($group->privacy === GroupPrivacy::Public || ($group->owner_id !== null && (int) $group->owner_id === (int) $viewer->getKey())) {
            return true;
        }

        return GroupMember::query()->where('group_id', $group->getKey())->where('user_id', $viewer->getKey())
            ->where('status', MembershipStatus::Active->value)->exists();
    }

    private function pageAllows(User $viewer, Event $event): bool
    {
        if ($event->page_id === null) {
            return true;
        }

        $page = $event->page;

        return $page !== null && ($page->isPubliclyVisible() || $page->isOwnedBy($viewer));
    }
}
