<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;

/**
 * « Nouveau message » (NOTIFICATIONS §2) : participant·es d'une conversation ; cloche et push, aperçu masqué
 * (« Nouveau message ») si la personne masque le contenu sensible. Pas d'e-mail (le résumé P3-07 s'en charge).
 */
final class NewMessage extends XomitonNotification
{
    public function __construct(
        public readonly int $conversationId,
        public readonly int $messageId,
        public readonly string $senderName,
        public readonly string $preview,
        public readonly ?string $conversationTitle = null,
        public readonly ?string $url = null,
    ) {}

    public function category(): string
    {
        return DatabaseNotification::CATEGORY_MESSAGES;
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
        $body = $this->conversationTitle === null
            ? __('notifications.new_message.body', ['name' => $this->senderName, 'preview' => $this->preview])
            : __('notifications.new_message.body_group', ['name' => $this->senderName, 'conversation' => $this->conversationTitle, 'preview' => $this->preview]);

        return [
            'title' => (string) __('notifications.new_message.title'),
            'body' => (string) $body,
            'url' => $this->url ?? self::urlTo('messages', [], '/messages'),
            'icon' => 'chat-left-text-fill',
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.new_message.discreet');
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['conversation_id' => $this->conversationId, 'message_id' => $this->messageId];
    }

    protected function subjectId(): int
    {
        return $this->conversationId;
    }

    protected function pushType(): string
    {
        return 'message';
    }
}
