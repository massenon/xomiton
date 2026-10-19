<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;

/**
 * « Invitation reçue » (P3-02, page Notifications du prototype) : personne invitée ; cloche et push, préférence « Communauté ».
 * Envoyée par App\Application\Community\Network\InvitationNotifier (P8-06) : `user_id` (compte qui invite, jamais son nom
 * réel) sert à ne pas renotifier une invitation annulée puis renvoyée.
 */
final class InvitationReceived extends XomitonNotification
{
    public function __construct(
        public readonly int $invitationId,
        public readonly string $fromName,
        public readonly ?string $url = null,
        public readonly ?int $fromUserId = null,
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
            'title' => (string) __('notifications.invitation_received.title'),
            'body' => (string) __('notifications.invitation_received.body', ['name' => $this->fromName]),
            'url' => $this->url ?? self::urlTo('qui-suivre', [], '/qui-suivre'),
            'icon' => 'person-plus',
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.invitation_received.discreet');
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['invitation_id' => $this->invitationId] + ($this->fromUserId !== null ? ['user_id' => $this->fromUserId] : []);
    }

    protected function subjectId(): int
    {
        return $this->invitationId;
    }

    protected function pushType(): string
    {
        return 'invitation';
    }
}
