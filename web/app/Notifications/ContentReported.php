<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;

/**
 * « Contenu signalé » (NOTIFICATIONS §2, §3) : équipe de modération ; cloche admin seulement. Sensible : le motif et la
 * nature du contenu ne s'affichent pas sur écran verrouillé en mode discret. Jamais l'extrait signalé dans la notification.
 */
final class ContentReported extends XomitonNotification
{
    public function __construct(
        public readonly int $reportId,
        public readonly string $kindLabel,
        public readonly string $reason,
        public readonly ?string $url = null,
    ) {}

    public function category(): string
    {
        return DatabaseNotification::CATEGORY_MODERATION;
    }

    public function isSensitive(): bool
    {
        return true;
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
            'title' => (string) __('notifications.content_reported.title'),
            'body' => (string) __('notifications.content_reported.body', ['kind' => $this->kindLabel, 'reason' => $this->reason]),
            'url' => $this->url ?? self::urlTo('admin.moderation', [], '/admin/moderation'),
            'icon' => 'flag-fill',
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.content_reported.discreet');
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['report_id' => $this->reportId];
    }

    protected function subjectId(): int
    {
        return $this->reportId;
    }

    protected function pushType(): string
    {
        return 'moderation';
    }
}
