<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;

/**
 * « SOS répétés depuis une même adresse » (P2-08 : limiteur `sos` en mode observateur, journal + notification admin au-delà
 * de `rate_limits.sos_log_per_minute_per_ip` envois par minute) : comptes `alerts.manage`, cloche admin seulement. Non
 * sensible : ni IP (seule son empreinte HMAC), ni contenu, ni personne ; le SOS lui-même n'est jamais refusé.
 */
final class SosBurstDetected extends XomitonNotification
{
    public function __construct(
        public readonly int $count,
        public readonly string $fingerprint,
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
            'title' => (string) __('notifications.sos_burst.title'),
            'body' => (string) __('notifications.sos_burst.body', ['count' => $this->count, 'fingerprint' => $this->fingerprint]),
            'url' => $this->url ?? self::urlTo('admin.alertes', [], '/admin/alertes'),
            'icon' => 'exclamation-octagon',
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.sos_burst.discreet');
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['count' => $this->count, 'fingerprint' => $this->fingerprint];
    }

    protected function pushType(): string
    {
        return 'sos_burst';
    }
}
