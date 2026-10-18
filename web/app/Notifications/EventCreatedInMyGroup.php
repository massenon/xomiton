<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;

/**
 * « Événement créé dans un de mes groupes » (P3-02) : membres du groupe ; cloche et push, préférence « Communauté ».
 * `dateLabel` et `timeLabel` sont déjà formatés (LocaleFormatter) par l'appelant.
 */
final class EventCreatedInMyGroup extends XomitonNotification
{
    public function __construct(
        public readonly int $eventId,
        public readonly string $title,
        public readonly string $dateLabel,
        public readonly string $timeLabel,
        public readonly string $groupName,
        public readonly ?string $url = null,
    ) {}

    public function category(): string
    {
        return DatabaseNotification::CATEGORY_COMMUNITY;
    }

    public function isSensitive(): bool
    {
        return false;
    }

    /**
     * @return array{title: string, body: string, url: string, icon: string}
     */
    protected function content(object $notifiable): array
    {
        return [
            'title' => (string) __('notifications.event_created.title'),
            'body' => (string) __('notifications.event_created.body_group', [
                'title' => $this->title,
                'group' => $this->groupName,
                'date' => $this->dateLabel,
                'time' => $this->timeLabel,
            ]),
            'url' => $this->url ?? self::urlTo('evenements.show', ['event' => $this->eventId], '/evenements/'.$this->eventId),
            'icon' => 'calendar-plus',
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.event_created.discreet');
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
