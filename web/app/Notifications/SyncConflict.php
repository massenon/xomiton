<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;

/**
 * « Conflit de synchronisation » (NOTIFICATIONS §2, §3, SOFTWARE_ARCHITECTURE §5) : administration ; cloche admin
 * seulement. Non sensible : le libellé du sujet est une référence (code de dossier, identifiant), jamais les valeurs en conflit.
 */
final class SyncConflict extends XomitonNotification
{
    public function __construct(
        public readonly int $conflictId,
        public readonly string $subjectLabel,
        public readonly string $field,
        public readonly ?string $url = null,
    ) {}

    public function category(): string
    {
        return DatabaseNotification::CATEGORY_SYSTEM;
    }

    public function isSensitive(): bool
    {
        return false;
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
            'title' => (string) __('notifications.sync_conflict.title'),
            'body' => (string) __('notifications.sync_conflict.body', ['subject' => $this->subjectLabel, 'field' => $this->field]),
            'url' => $this->url ?? self::urlTo('admin.synchronisation', [], '/admin/synchronisation'),
            'icon' => 'arrow-repeat',
        ];
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['conflict_id' => $this->conflictId];
    }

    protected function subjectId(): int
    {
        return $this->conflictId;
    }

    protected function pushType(): string
    {
        return 'sync_conflict';
    }
}
