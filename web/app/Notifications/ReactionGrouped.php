<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;
use InvalidArgumentException;

/**
 * « Réaction ou commentaire » (NOTIFICATIONS §2, §4.2) : auteur·e de la publication ; cloche seulement, regroupée
 * (« Estelle et 4 autres ont aimé… »). `kind` : like, comment ou reply ; `othersCount` : nombre d'autres personnes.
 */
final class ReactionGrouped extends XomitonNotification
{
    public const KIND_LIKE = 'like';

    public const KIND_COMMENT = 'comment';

    public const KIND_REPLY = 'reply';

    /** @var list<string> */
    public const KINDS = [self::KIND_LIKE, self::KIND_COMMENT, self::KIND_REPLY];

    public function __construct(
        public readonly int $postId,
        public readonly string $kind,
        public readonly string $actorName,
        public readonly int $othersCount = 0,
        public readonly ?string $url = null,
    ) {
        if (! in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown reaction kind [%s].', $kind));
        }
    }

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
        $replace = ['name' => $this->actorName, 'count' => $this->othersCount];
        $many = $this->othersCount > 0;

        $body = match ($this->kind) {
            self::KIND_LIKE => __($many ? 'notifications.reaction.liked_many' : 'notifications.reaction.liked_one', $replace),
            self::KIND_COMMENT => __($many ? 'notifications.reaction.commented_many' : 'notifications.reaction.commented_one', $replace),
            default => __('notifications.reaction.replied', $replace),
        };

        return [
            'title' => (string) __('notifications.reaction.title'),
            'body' => (string) $body,
            'url' => $this->url ?? self::urlTo('fil.show', ['post' => $this->postId], '/fil/'.$this->postId),
            'icon' => $this->kind === self::KIND_LIKE ? 'hand-thumbs-up' : 'chat-dots',
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.reaction.discreet');
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['post_id' => $this->postId, 'kind' => $this->kind, 'count' => $this->othersCount + 1];
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
