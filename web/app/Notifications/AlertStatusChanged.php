<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;

/**
 * « Ton signalement a avancé » (P5-03) : l'auteur·e non anonyme d'une alerte est prévenu·e d'un changement de statut
 * (cloche et push, préférences « alertes »). Sensible. Registre « tu ».
 */
final class AlertStatusChanged extends XomitonNotification
{
    public function __construct(
        public readonly int $alertId,
        public readonly string $code,
        public readonly string $statusLabel,
        public readonly ?string $url = null,
    ) {}

    public function category(): string
    {
        return DatabaseNotification::CATEGORY_ALERTS;
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
            'title' => (string) __('notifications.alert_status_changed.title'),
            'body' => (string) __('notifications.alert_status_changed.body', ['code' => $this->code, 'status' => $this->statusLabel]),
            'url' => $this->url ?? self::urlTo('signaler', [], '/signaler').'#suivi',
            'icon' => 'arrow-repeat',
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.alert_status_changed.discreet');
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['alert_id' => $this->alertId];
    }

    protected function subjectId(): int
    {
        return $this->alertId;
    }

    protected function pushType(): string
    {
        return 'alert';
    }
}
