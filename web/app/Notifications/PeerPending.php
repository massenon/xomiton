<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;
use App\Notifications\Concerns\MasksInDiscreetMode;

/**
 * « Inscription à valider » (P15-19, type de push `peer_pending`) : un compte attend la validation de l'équipe
 * (toute inscription depuis le 11/11/2026 : « tout valider » par défaut, Q26 ; aussi inscription signalée D-MOB-17, pair·e invité·e
 * qui a terminé son inscription, RG-03), adressé aux comptes habilités `users.validate_peers`. Le texte porte le pseudonyme et le
 * type (membre, ou allié·e et son statut) ; le lien ouvre l'écran Inscriptions. Cloche et push selon les préférences ;
 * l'application ouvre « Inscriptions à valider ». Sensible : texte neutre quand les aperçus sont masqués ou en mode
 * discret. Jamais le motif du signalement, l'adresse, l'orientation ni l'identité de genre.
 */
final class PeerPending extends XomitonNotification
{
    use MasksInDiscreetMode;

    /**
     * @param  string|null  $kind  type de la personne inscrite, déjà en clair (« membre », « allié·e : avocat·e »…) ; null = non précisé
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $pseudo,
        public readonly ?string $kind = null,
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
            'body' => $this->kind === null || $this->kind === ''
                ? (string) __('notifications.peer_pending.body', ['name' => $this->pseudo])
                : (string) __('notifications.peer_pending.body_typed', ['name' => $this->pseudo, 'type' => $this->kind]),
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
