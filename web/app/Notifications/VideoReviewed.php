<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;

/**
 * Décision de la modération sur une vidéo envoyée (Q96) : « Ta vidéo a été publiée » ou refus avec le motif écrit pour la
 * personne (libellé du motif et texte libre ≤ 300 car.), délai avant suppression du fichier compris. Cloche seulement,
 * catégorie sécurité du compte (décision de l'équipe, non désactivable, comme ModerationNotice). Un refus est sensible :
 * texte neutre sur écran verrouillé. Jamais le nom de la personne qui a décidé.
 */
final class VideoReviewed extends XomitonNotification
{
    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public function __construct(
        public readonly string $decision,
        public readonly int $postId,
        public readonly ?string $reason = null,
        public readonly ?string $url = null,
        public readonly int $retentionDays = 7,
    ) {}

    public function category(): string
    {
        return DatabaseNotification::CATEGORY_SECURITY;
    }

    public function isSensitive(): bool
    {
        return $this->decision === self::REJECTED;
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
        $key = $this->decision === self::APPROVED ? 'approved' : 'rejected';

        return [
            'title' => (string) __('notifications.video_reviewed.'.$key.'.title'),
            'body' => (string) __('notifications.video_reviewed.'.$key.'.body', [
                'reason' => $this->reason !== null && trim($this->reason) !== '' ? trim($this->reason) : (string) __('notifications.moderation.no_reason'),
                'days' => $this->retentionDays,
            ]),
            'url' => $this->url ?? url('/fil'),
            'icon' => $this->decision === self::APPROVED ? 'camera-video-fill' : 'camera-video-off-fill',
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.video_reviewed.discreet');
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['decision' => $this->decision, 'post_id' => $this->postId];
    }

    protected function subjectId(): int
    {
        return $this->postId;
    }

    protected function pushType(): string
    {
        return 'video_reviewed';
    }
}
