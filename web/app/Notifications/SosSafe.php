<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;

/**
 * « Personne en sécurité » (P5-02 MarkSafe, P5-03) : envoyé aux personnes déjà prévenues d'un SOS (cloche et push pour
 * les comptes, un SMS au contact de confiance et aux fiches sans compte). Catégorie SOS, sensible. Registre « vous ».
 */
final class SosSafe extends XomitonNotification
{
    public function __construct(
        public readonly int $alertId,
        public readonly string $code,
        public readonly ?string $url = null,
    ) {}

    public function category(): string
    {
        return DatabaseNotification::CATEGORY_SOS;
    }

    public function isSensitive(): bool
    {
        return true;
    }

    /**
     * @return array{title: string, body: string, url: string|null, icon: string}
     */
    protected function content(object $notifiable): array
    {
        return [
            'title' => (string) __('notifications.sos_safe.title'),
            'body' => (string) __('notifications.sos_safe.body', ['code' => $this->code]),
            'url' => $this->url,
            'icon' => 'shield-check',
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.sos_safe.discreet');
    }

    protected function smsText(object $notifiable): ?string
    {
        return (string) __('notifications.sos_safe.sms', ['code' => $this->code]);
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['alert_id' => $this->alertId, 'code' => $this->code];
    }

    protected function subjectId(): int
    {
        return $this->alertId;
    }

    protected function pushType(): string
    {
        return 'sos';
    }
}
