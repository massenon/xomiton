<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;

/**
 * « Réponse dans un sujet de forum » (P3-02) : auteur·e du sujet ; cloche et push, préférence « Communauté ».
 */
final class ForumReplyPosted extends XomitonNotification
{
    public function __construct(
        public readonly int $topicId,
        public readonly int $replyId,
        public readonly string $authorName,
        public readonly string $topicTitle,
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
            'title' => (string) __('notifications.forum_reply.title'),
            'body' => (string) __('notifications.forum_reply.body', ['name' => $this->authorName, 'title' => $this->topicTitle]),
            'url' => $this->url ?? self::urlTo('forums.show', ['topic' => $this->topicId], '/forums/'.$this->topicId),
            'icon' => 'chat-square-text',
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.forum_reply.discreet');
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['topic_id' => $this->topicId, 'reply_id' => $this->replyId];
    }

    protected function subjectId(): int
    {
        return $this->topicId;
    }

    protected function pushType(): string
    {
        return 'forum_topic';
    }
}
