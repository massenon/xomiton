<?php

declare(strict_types=1);

namespace App\Application\Community\Events;

use App\Application\Community\Data\EventDraft;
use App\Application\Community\UniqueSlug;
use App\Application\Feed\BadWordFilter;
use App\Application\Seo\SitemapBuilder;
use App\Domain\Feed\Exceptions\ContentBlocked;
use App\Enums\ContentStatus;
use App\Enums\MembershipStatus;
use App\Models\Event;
use App\Models\GroupMember;
use App\Models\User;
use App\Notifications\EventCreatedInMyGroup;
use App\Support\Audit;
use App\Support\LocaleFormatter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Créer un événement (P8-04, events.php l.8-15 et modale partials/modals.php l.166-190) : filtre de mots sur le titre, la
 * description, la ville et l'adresse (terme bloquant → 422 `content_blocked`), slug unique, publié aussitôt (comme le
 * prototype ; la modération peut le masquer), la personne qui crée en est l'organisatrice. Adresse exacte chiffrée
 * (cast `encrypted`), servie aux seul·es participant·es confirmé·es. Ouvert aux visiteur·ses (`is_public`) seulement si
 * la modération le demande (EventPolicy::publishPublicly, vérifié par la requête) : le plan du site est alors invalidé.
 * Événement d'un groupe : cloche et push « Événement créé dans un de mes groupes » (EventCreatedInMyGroup, préférence
 * « Communauté ») aux membres actives, sans l'organisatrice. Journal `event.created` (et `event.words_flagged`).
 */
final class CreateEvent
{
    public function __construct(private readonly BadWordFilter $filter) {}

    /**
     * @throws ContentBlocked
     */
    public function __invoke(User $organizer, EventDraft $draft): Event
    {
        $verdict = $this->filter->assertAllowed([
            'title' => $draft->title,
            'description' => $draft->description,
            'place' => $draft->place,
            'address' => $draft->address,
        ]);

        $event = Event::query()->create([
            'title' => trim($draft->title),
            'slug' => UniqueSlug::for(Event::class, $draft->title, 200),
            'category_id' => $draft->categoryId,
            'starts_at' => $draft->startsAt,
            'ends_at' => $draft->endsAt,
            'place' => self::clean($draft->place),
            'address' => self::clean($draft->address),
            'is_online' => $draft->isOnline,
            'online_url' => $draft->isOnline ? self::clean($draft->onlineUrl) : null,
            'description' => self::clean($draft->description),
            'organizer_id' => $organizer->getKey(),
            'group_id' => $draft->groupId,
            'going_count' => 0,
            'status' => ContentStatus::Published,
            'is_public' => $draft->isPublic,
        ]);

        Audit::log('event.created', $event, null, $organizer);

        if ($verdict->isFlagged()) {
            Audit::log('event.words_flagged', $event, (string) __('feed.moderation.auto_details', ['terms' => implode(', ', $verdict->flaggingTerms)]), $organizer);
        }

        if ($event->is_public) {
            SitemapBuilder::flush();
        }

        if ($event->group_id !== null) {
            $this->notifyGroup($event, $organizer);
        }

        return $event;
    }

    private function notifyGroup(Event $event, User $organizer): void
    {
        $group = $event->group;

        if ($group === null) {
            return;
        }

        $fmt = LocaleFormatter::make();
        $notification = new EventCreatedInMyGroup(
            eventId: (int) $event->getKey(),
            title: (string) $event->title,
            dateLabel: $fmt->longDateWithDay($event->starts_at),
            timeLabel: $fmt->time($event->starts_at),
            groupName: (string) $group->name,
            url: EventLinks::show($event),
        );

        EventAccess::withoutBlocksWith(GroupMember::query(), 'user_id', (int) $organizer->getKey())
            ->where('group_id', $group->getKey())->where('status', MembershipStatus::Active->value)
            ->where('user_id', '!=', $organizer->getKey())
            ->with('user')
            ->chunkById(100, static function ($members) use ($notification, $event): void {
                $users = $members->pluck('user')->filter(static fn (?User $user): bool => $user instanceof User && $user->isActive());

                try {
                    Notification::send($users, $notification);
                } catch (Throwable $exception) {
                    Log::warning('Événements : « Événement créé dans un de mes groupes » non envoyé.', ['event_id' => $event->getKey(), 'error' => $exception->getMessage()]);
                }
            });
    }

    private static function clean(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === null || $value === '' ? null : $value;
    }
}
