<?php

declare(strict_types=1);

namespace App\Application\Community\Events;

use App\Domain\Content\Exceptions\ContentRefused;
use App\Enums\ContentStatus;
use App\Models\Event;
use App\Models\User;
use App\Notifications\TeamNotice;
use App\Support\Audit;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Décision sur un événement en attente (P18-14, file d'approbation facultative, `/admin/evenements`, `moderation.manage`) :
 * approuver le publie (plan du site, cloche du groupe comme à la création) ; refuser le masque, motif obligatoire écrit
 * pour l'organisateur·rice. L'organisateur·rice est notifié·e (TeamNotice) ; journal `event.approved|refused` sans texte.
 */
final class ReviewEvent
{
    public const REASON_MAX = 500;

    public function __construct(private readonly CreateEvent $create) {}

    /**
     * @throws ContentRefused
     */
    public function approve(Event $event, User $actor): void
    {
        $this->assertPending($event);
        $event->forceFill(['status' => ContentStatus::Published])->save();
        Audit::log('event.approved', $event, null, $actor);

        $organizer = $event->organizer;

        if ($organizer !== null) {
            $this->create->announce($event, $organizer);
        }

        $this->notify($event, new TeamNotice(TeamNotice::EVENT_APPROVED, (string) $event->title, null, EventLinks::show($event)));
    }

    /**
     * @throws ContentRefused
     */
    public function refuse(Event $event, string $reason, User $actor): void
    {
        $this->assertPending($event);
        $event->forceFill(['status' => ContentStatus::Hidden])->save();
        Audit::log('event.refused', $event, null, $actor);
        $this->notify($event, new TeamNotice(TeamNotice::EVENT_REFUSED, (string) $event->title, trim($reason)));
    }

    /**
     * @throws ContentRefused
     */
    private function assertPending(Event $event): void
    {
        if (! $event->isPending()) {
            throw new ContentRefused('event_decided');
        }
    }

    private function notify(Event $event, TeamNotice $notice): void
    {
        $organizer = $event->organizer;

        if ($organizer === null || ! $organizer->isActive()) {
            return;
        }

        try {
            $organizer->notify($notice);
        } catch (Throwable $exception) {
            Log::warning('Événements : décision non notifiée.', ['event_id' => $event->getKey(), 'error' => $exception->getMessage()]);
        }
    }
}
