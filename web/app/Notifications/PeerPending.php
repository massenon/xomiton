<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;
use App\Notifications\Concerns\MasksInDiscreetMode;

/**
 * « Inscription à valider » (P15-19, type de push `peer_pending`) : un compte attend la validation de l'équipe
 * (inscription signalée D-MOB-17, « tout valider » Q26, pair·e invité·e qui a terminé son inscription, RG-03),
 * adressé aux comptes habilités `users.validate_peers`. Cloche et push ; l'application ouvre « Inscriptions à valider ».
 * Sensible : texte neutre quand les aperçus sont masqués ou en mode discret. Jamais le motif du signalement ni l'adresse.
 */
final class PeerPending extends XomitonNotification
{
    use MasksInDiscreetMode;

    public function __construct(
        public readonly int $userId,
        public readonly string $pseudo,
    ) {}

    public function category(): string
    {
        return DatabaseNotification::CATEGORY_SYSTEM;
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
        return [
            'title' => (string) __('notifications.peer_pending.title'),
            'body' => (string) __('notifications.peer_pending.body', ['name' => $this->pseudo]),
            'url' => self::urlTo('admin.inscriptions', [], '/admin/inscriptions'),
            'icon' => 'person-check-fill',
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.peer_pending.discreet');
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['user_id' => $this->userId];
    }

    protected function subjectId(): int
    {
        return $this->userId;
    }

    protected function pushType(): string
    {
        return 'peer_pending';
    }
}
