<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;

/**
 * « Règles de validation modifiées » (P15-19, P15-20) : push **silencieux** (type `rules`, données seules, FCM
 * seulement) adressé aux pair·es quand l'administration change une règle de validation ; l'application relit
 * `GET pair/validation-rules` (ETag). Aucun texte, aucune cloche, aucune préférence : la personne ne voit rien. Le
 * `heartbeat` (`rules_updated_at`) rattrape un push manqué.
 */
final class RulesUpdated extends XomitonNotification
{
    public function category(): string
    {
        return DatabaseNotification::CATEGORY_SYSTEM;
    }

    public function isSensitive(): bool
    {
        return false;
    }

    protected function isSilent(): bool
    {
        return true;
    }

    protected function wantsDatabase(): bool
    {
        return false;
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function content(object $notifiable): array
    {
        return ['title' => '', 'body' => ''];
    }

    protected function pushType(): string
    {
        return 'rules';
    }
}
