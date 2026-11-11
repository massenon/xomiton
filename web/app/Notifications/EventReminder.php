<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;

/**
 * « Rappel d'événement (veille) » (NOTIFICATIONS §2) : participant·es ; cloche, push et e-mail `rappel-evenement`.
 * `timeLabel` et `dateLabel` sont déjà formatés (LocaleFormatter) par l'appelant ; `place` est nul pour un événement en ligne.
 */
final class EventReminder extends XomitonNotification
{
    public function __construct(
        public readonly int $eventId,
        public readonly string $title,
        public readonly string $timeLabel,
        public readonly ?string $place,
        public readonly string $dateLabel,
        public readonly ?string $url = null,
    ) {}

    public function category(): string
    {
        return DatabaseNotification::CATEGORY_EVENTS;
    }

    public function isSensitive(): bool
    {
        return false;
    }

    public function mailTemplate(): ?string
    {
        return 'rappel-evenement';
    }

    protected function mailContext(): ?string
    {
        return 'rappel_evenement';
    }

    /**
     * @return array<string, scalar|\Stringable|null>
     */
    protected function mailVariables(object $notifiable): array
    {
        return [
            'event_title' => $this->title,
            'event_date' => $this->dateLabel,
        ];
    }

    /**
     * @return array{title: string, body: string, url: string, icon: string}
     */
    protected function content(object $notifiable): array
    {
        $body = $this->place === null
            ? __('notifications.event_reminder.body_online', ['time' => $this->timeLabel])
            : __('notifications.event_reminder.body', ['time' => $this->timeLabel, 'place' => $this->place]);

        return [
            'title' => (string) __('notifications.event_reminder.title', ['title' => $this->title]),
            'body' => (string) $body,
            'url' => $this->url ?? self::urlTo('evenements.show', ['event' => $this->eventId], '/evenements/'.$this->eventId),
            'icon' => 'calendar-event',
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.event_reminder.discreet');
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['event_id' => $this->eventId];
    }

    protected function subjectId(): int
    {
        return $this->eventId;
    }

    protected function pushType(): string
    {
        return 'event';
    }
}
