<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;
use App\Notifications\Concerns\MasksInDiscreetMode;

/**
 * Nouvelle demande du rôle pair (D-MOB-15), adressée aux comptes habilités à l'attribuer (`peers.assign`) : cloche et push
 * de l'administration, ouverture sur la file des inscriptions (type de push `peer_pending`, P15-19). Sensible : texte neutre
 * quand les aperçus sont masqués ou en mode discret. Jamais le motif de la personne dans la notification.
 */
final class RoleRequested extends XomitonNotification
{
    use MasksInDiscreetMode;

    public function __construct(
        public readonly int $requestId,
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
            'title' => (string) __('notifications.role_requested.title'),
            'body' => (string) __('notifications.role_requested.body', ['name' => $this->pseudo]),
            'url' => self::urlTo('admin.inscriptions', [], '/admin/inscriptions'),
            'icon' => 'person-plus-fill',
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.role_requested.discreet');
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['role_request_id' => $this->requestId];
    }

    protected function subjectId(): int
    {
        return $this->requestId;
    }

    protected function pushType(): string
    {
        return 'peer_pending';
    }
}
