<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;

/**
 * « Demande d'adhésion à un groupe » (P3-02) : administrateur·rices du groupe ; cloche et push, préférence « Communauté ».
 * Sensible (P8-02, RG-02, Q75) : le nom d'un groupe peut révéler une orientation ou une identité ; en mode discret, cloche
 * et push n'affichent que « Demande d'adhésion », le nom n'apparaît qu'une fois l'application ouverte.
 */
final class GroupJoinRequested extends XomitonNotification
{
    public function __construct(
        public readonly int $groupId,
        public readonly int $requesterId,
        public readonly string $requesterName,
        public readonly string $groupName,
        public readonly ?string $url = null,
    ) {}

    public function category(): string
    {
        return DatabaseNotification::CATEGORY_COMMUNITY;
    }

    public function isSensitive(): bool
    {
        return true;
    }

    /**
     * @return array{title: string, body: string, url: string, icon: string}
     */
    protected function content(object $notifiable): array
    {
        return [
            'title' => (string) __('notifications.group_request.title'),
            'body' => (string) __('notifications.group_request.body', ['name' => $this->requesterName, 'group' => $this->groupName]),
            'url' => $this->url ?? self::urlTo('groupes.show', ['group' => $this->groupId], '/groupes/'.$this->groupId),
            'icon' => 'people',
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.group_request.discreet');
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['group_id' => $this->groupId, 'requester_id' => $this->requesterId];
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
