<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;

/**
 * « Sondage terminé » (NOTIFICATIONS §2) : auteur·e du sondage ; cloche seulement.
 */
final class PollEnded extends XomitonNotification
{
    public function __construct(
        public readonly int $pollId,
        public readonly int $postId,
        public readonly string $question,
        public readonly int $votes,
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

    protected function wantsPush(): bool
    {
        return false;
    }

    /**
     * @return array{title: string, body: string, url: string, icon: string}
     */
    protected function content(object $notifiable): array
    {
        return [
            'title' => (string) __('notifications.poll_closed.title'),
            'body' => (string) __('notifications.poll_closed.body', ['question' => $this->question, 'votes' => $this->votes]),
            'url' => $this->url ?? self::urlTo('fil.show', ['post' => $this->postId], '/fil/'.$this->postId),
            'icon' => 'bar-chart-fill',
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.poll_closed.discreet');
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['poll_id' => $this->pollId, 'post_id' => $this->postId, 'votes' => $this->votes];
    }

    protected function subjectId(): int
    {
        return $this->postId;
    }

    protected function pushType(): string
    {
        return 'post';
    }
}
