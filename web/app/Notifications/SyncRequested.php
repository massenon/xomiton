<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;

/**
 * « Lance une synchronisation » (P15-19) : push **silencieux** (type `sync`, données seules, FCM seulement) adressé à
 * un·e pair·e quand quelqu'un d'autre a changé ce qu'il·elle a collecté (dossier modifié, archivé, rétabli par
 * l'administration, conflit tranché) : l'application lance un cycle `sync/pull` et `sync/push`. Aucun texte, aucune
 * cloche, aucune préférence. Jamais d'identifiant de dossier dans la charge.
 */
final class SyncRequested extends XomitonNotification
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
        return 'sync';
    }
}
