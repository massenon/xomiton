<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;
use App\Models\User;

/**
 * « Inscription créée » (NOTIFICATIONS §2) : e-mail `bienvenue` seulement, envoyé dès la phase 1 (Q44 a : il rappelle
 * le code de vérification). Émise par App\Application\Auth\RegisterUser à la place d'un appel direct au service
 * d'e-mails : un seul envoi, aucune ligne dans la cloche. Sécurité du compte : jamais désactivable.
 */
final class UserRegistered extends XomitonNotification
{
    public function category(): string
    {
        return DatabaseNotification::CATEGORY_SECURITY;
    }

    public function isSensitive(): bool
    {
        return false;
    }

    protected function wantsDatabase(): bool
    {
        return false;
    }

    protected function wantsPush(): bool
    {
        return false;
    }

    public function mailTemplate(): ?string
    {
        return 'bienvenue';
    }

    protected function mailContext(): ?string
    {
        return 'inscription';
    }

    /**
     * @return array<string, scalar|\Stringable|null>
     */
    protected function mailVariables(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return [];
        }

        // Le nom réel n'est pas connu à l'inscription : le pseudo tient lieu de prénom (comme avant P3-02).
        return [
            'user_first_name' => (string) $notifiable->pseudo,
            'user_pseudo' => (string) $notifiable->pseudo,
            'user_email' => (string) $notifiable->email,
        ];
    }

    /**
     * @return array{title: string, body: string, url: string, icon: string}
     */
    protected function content(object $notifiable): array
    {
        return [
            'title' => (string) __('notifications.account_created.title'),
            'body' => (string) __('notifications.account_created.body'),
            'url' => self::urlTo('fil', [], '/fil'),
            'icon' => 'person-check',
        ];
    }
}
