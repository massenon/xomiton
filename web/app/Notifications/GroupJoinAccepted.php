<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;

/**
 * « Adhésion acceptée » (P3-02) : personne admise dans le groupe ; cloche et push, préférence « Communauté ».
 */
final class GroupJoinAccepted extends XomitonNotification
{
    public function __construct(
        public readonly int $groupId,
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
            'title' => (string) __('notifications.group_accepted.title'),
            'body' => (string) __('notifications.group_accepted.body', ['group' => $this->groupName]),
            'url' => $this->url ?? self::urlTo('groupes.show', ['group' => $this->groupId], '/groupes/'.$this->groupId),
            'icon' => 'people-fill',
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.group_accepted.discreet');
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['group_id' => $this->groupId];
    }

    protected function subjectId(): int
    {
        return $this->groupId;
    }

    protected function pushType(): string
    {
        return 'group';
    }
}
