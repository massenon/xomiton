<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;
use App\Models\User;
use App\Notifications\Concerns\MasksInDiscreetMode;

/**
 * « Contenu signalé » (NOTIFICATIONS §2, §3) : équipe de modération ; cloche admin, et push FCM (type `content_reported`,
 * P15-19) pour les comptes qui ont l'espace Admin de l'application mobile (`admin_mobile.access`) seulement — jamais de
 * Web Push, jamais de push pour un·e modérateur·rice sans espace Admin. Priorité normale : un signalement ne réveille personne. Sensible : le motif et la
 * nature du contenu ne s'affichent pas sur écran verrouillé en mode discret. Jamais l'extrait signalé dans la notification.
 */
final class ContentReported extends XomitonNotification
{
    use MasksInDiscreetMode;

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
        return true;
    }

    protected function wantsWebPush(object $notifiable): bool
    {
        return false;
    }

    protected function wantsFcm(object $notifiable): bool
    {
        return $notifiable instanceof User && $notifiable->can('admin_mobile.access');
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
        return 'content_reported';
    }
}
