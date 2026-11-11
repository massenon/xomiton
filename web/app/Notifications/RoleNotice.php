<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;

/**
 * Changement de rôle ou décision sur une demande de rôle, adressé à la personne concernée (décisions client du
 * 2026-10-29, journal `roles.*`) : rôle pair ou admin donné ou retiré, demande de pair refusée (avec le motif écrit pour
 * elle). Cloche et push (le mobile relit alors `GET me` pour ajuster le sélecteur d'espaces) ; catégorie sécurité du
 * compte, donc non désactivable. Jamais le nom de la personne qui a décidé.
 */
final class RoleNotice extends XomitonNotification
{
    public const PEER_GRANTED = 'peer_granted';

    public const PEER_REVOKED = 'peer_revoked';

    public const PEER_REFUSED = 'peer_refused';

    public const ADMIN_GRANTED = 'admin_granted';

    public const ADMIN_REVOKED = 'admin_revoked';

    /** @var list<string> */
    public const KINDS = [self::PEER_GRANTED, self::PEER_REVOKED, self::PEER_REFUSED, self::ADMIN_GRANTED, self::ADMIN_REVOKED];

    public function __construct(
        public readonly string $kind,
        public readonly ?string $reason = null,
    ) {}

    public function category(): string
    {
        return DatabaseNotification::CATEGORY_SECURITY;
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
        $reason = $this->reason !== null && trim($this->reason) !== '' ? trim($this->reason) : (string) __('notifications.role.no_reason');

        return [
            'title' => (string) __('notifications.role.'.$this->kind.'.title'),
            'body' => (string) __('notifications.role.'.$this->kind.'.body', ['reason' => $reason]),
            'url' => match ($this->kind) {
                self::PEER_GRANTED => self::urlTo('espace-pair', [], '/espace-pair'),
                self::ADMIN_GRANTED => self::urlTo('admin.dashboard', [], '/admin'),
                default => self::urlTo('fil', [], '/fil'),
            },
            'icon' => match ($this->kind) {
                self::PEER_GRANTED, self::ADMIN_GRANTED => 'patch-check-fill',
                self::PEER_REFUSED => 'x-circle-fill',
                default => 'person-dash-fill',
            },
        ];
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['decision' => $this->kind];
    }

    protected function pushType(): string
    {
        return 'role_changed';
    }
}
