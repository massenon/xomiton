<?php

declare(strict_types=1);

namespace App\Application\Account\Profile;

use App\Application\Community\Events\EventAccess;
use App\Application\Community\Events\EventDirectory;
use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Onglet « Événements » du profil (P8-07, prototype `my-profile-events.php`) : mêmes règles de lecture que `/evenements`
 * (EventAccess::forMember : publiés, groupe et page visibles, blocages ; adresse exacte réservée aux participant·es,
 * EventResource).
 *
 *  - son propre profil : événements auxquels la personne participe (« Je participe ») ou qu'elle organise, à venir
 *    d'abord, puis « À venir pour toi » (prochains événements auxquels elle ne participe pas encore) ;
 *  - le profil d'une autre personne : seulement les événements qu'elle **organise** — sa participation à un
 *    événement n'est jamais exposée (un événement de la communauté LBTQ+ révèle beaucoup de qui y va).
 */
final class ProfileEvents
{
    public const SUGGESTIONS = 3;

    public function __construct(
        private readonly EventAccess $access,
        private readonly EventDirectory $directory,
    ) {}

    /**
     * @return array{events: Collection<int, Event>, suggestions: Collection<int, Event>}
     */
    public function for(User $viewer, User $owner, int $limit): array
    {
        $ownerId = (int) $owner->getKey();
        $self = (int) $viewer->getKey() === $ownerId;

        $query = $this->access->forMember(Event::query(), $viewer)->with(['category', 'group', 'organizer:id,pseudo']);

        if ($self) {
            $going = EventRsvp::query()->select('event_id')->where('user_id', $ownerId)->where('status', RsvpStatus::Going->value);
            $query->where(static fn ($q) => $q->where('events.organizer_id', $ownerId)->orWhereIn('events.id', $going));
        } else {
            $query->where('events.organizer_id', $ownerId);
        }

        $upcoming = (clone $query)->upcoming()->limit($limit)->get();
        $past = $upcoming->count() < $limit ? (clone $query)->past()->limit($limit - $upcoming->count())->get() : collect();
        $events = $upcoming->concat($past)->values();

        $suggestions = $self
            ? $this->directory->query($viewer)->whereNotIn('events.id', $events->modelKeys())->limit(self::SUGGESTIONS)->get()
            : collect();

        return ['events' => $events, 'suggestions' => $suggestions];
    }
}
