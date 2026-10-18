<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;

/**
 * « Invitation acceptée » (P3-02) : personne qui avait invité ; cloche et push, préférence « Communauté ».
 */
final class InvitationAccepted extends XomitonNotification
{
    public function __construct(
        public readonly int $invitationId,
        public readonly int $acceptedById,
        public readonly string $name,
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
            'title' => (string) __('notifications.invitation_accepted.title'),
            'body' => (string) __('notifications.invitation_accepted.body', ['name' => $this->name]),
            'url' => $this->url ?? self::urlTo('qui-suivre', [], '/qui-suivre'),
            'icon' => 'person-check',
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.invitation_accepted.discreet');
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['invitation_id' => $this->invitationId, 'user_id' => $this->acceptedById];
    }

    protected function subjectId(): int
    {
        return $this->acceptedById;
    }

    protected function pushType(): string
    {
        return 'profile';
    }
}
