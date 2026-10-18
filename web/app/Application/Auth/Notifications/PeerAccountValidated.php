<?php

declare(strict_types=1);

namespace App\Application\Auth\Notifications;

use App\Models\DatabaseNotification;
use App\Models\User;
use App\Notifications\XomitonNotification;

/**
 * « Compte pair validé » (NOTIFICATIONS §2) : cloche et e-mail `compte-valide` (canal `mail` par template), portés
 * par la même notification depuis P3-02. Catégorie sécurité du compte : non désactivable. Aucune donnée sensible.
 */
final class PeerAccountValidated extends XomitonNotification
{
    private readonly int $validatedById;

    public function __construct(User|int $validatedBy)
    {
        $this->validatedById = $validatedBy instanceof User ? (int) $validatedBy->getKey() : $validatedBy;
    }

    public function category(): string
    {
        return DatabaseNotification::CATEGORY_SECURITY;
    }

    public function isSensitive(): bool
    {
        return false;
    }

    public function mailTemplate(): ?string
    {
        return 'compte-valide';
    }

    protected function mailContext(): ?string
    {
        return 'validation_pair';
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
            'title' => (string) __('auth.peer_validated.title'),
            'body' => (string) __('auth.peer_validated.body'),
            'url' => self::urlTo('espace-pair', [], '/espace-pair'),
            'icon' => 'patch-check-fill',
        ];
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['validated_by' => $this->validatedById];
    }
}
