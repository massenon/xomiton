<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;
use App\Notifications\Concerns\MasksInDiscreetMode;

/**
 * « Vidéo à relire » (Q96) : équipe de modération (`moderation.manage`), à chaque vidéo qui arrive en relecture ; cloche
 * seulement (aucun push : une relecture n'a pas à réveiller), lien vers l'écran « Vidéos à relire ». Jamais le nom de
 * l'auteur·rice ni la description dans la notification.
 */
final class VideoPendingReview extends XomitonNotification
{
    use MasksInDiscreetMode;

    public function __construct(public readonly int $postId, public readonly ?string $url = null) {}

    public function category(): string
    {
        return DatabaseNotification::CATEGORY_MODERATION;
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
            'title' => (string) __('notifications.video_pending.title'),
            'body' => (string) __('notifications.video_pending.body'),
            'url' => $this->url ?? self::urlTo('admin.moderation.videos', [], '/admin/moderation/videos'),
            'icon' => 'camera-video-fill',
        ];
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['post_id' => $this->postId];
    }

    protected function subjectId(): int
    {
        return $this->postId;
    }

    protected function pushType(): string
    {
        return 'video_pending_review';
    }
}
