<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;
use App\Notifications\Concerns\TargetsMobileSpace;

/**
 * « SOS non accusé » (NOTIFICATIONS §2, RG-05) : point focal national après le délai d'escalade ; cloche, push, SMS
 * (toujours, c'est l'escalade) et e-mail `alerte-routee`. Catégorie SOS : non désactivable. Sensible.
 */
final class SosUnacknowledged extends XomitonNotification
{
    use TargetsMobileSpace;

    public function __construct(
        public readonly int $alertId,
        public readonly string $code,
        public readonly int $minutes,
        public readonly ?string $zoneLabel = null,
        public readonly ?string $url = null,
        // Espace mobile du destinataire (`pair`, `admin`, null) et ULID de l'alerte : type et `id` du push (P15-19).
        public readonly ?string $audience = null,
        public readonly ?string $alertUlid = null,
    ) {}

    public function category(): string
    {
        return DatabaseNotification::CATEGORY_SOS;
    }

    public function isSensitive(): bool
    {
        return true;
    }

    public function mailTemplate(): ?string
    {
        return 'alerte-routee';
    }

    protected function mailContext(): ?string
    {
        return 'sos_escalade';
    }

    /**
     * @return array<string, scalar|\Stringable|null>
     */
    protected function mailVariables(object $notifiable): array
    {
        return [
            'alert_reference' => $this->code,
            'alert_zone' => $this->zoneLabel ?? '—',
        ];
    }

    /**
     * @return array{title: string, body: string, url: string, icon: string}
     */
    protected function content(object $notifiable): array
    {
        return [
            'title' => (string) __('notifications.sos_unacknowledged.title', ['minutes' => $this->minutes]),
            'body' => (string) __('notifications.sos_unacknowledged.body', ['code' => $this->code]),
            'url' => $this->targetUrl(),
            'icon' => 'exclamation-octagon-fill',
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.sos_unacknowledged.discreet');
    }

    protected function wantsSms(object $notifiable): bool
    {
        return true;
    }

    protected function smsText(object $notifiable): ?string
    {
        return (string) __('notifications.sos_unacknowledged.sms', [
            'code' => $this->code,
            'minutes' => $this->minutes,
            'url' => $this->targetUrl(),
        ]);
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['alert_id' => $this->alertId, 'code' => $this->code, 'minutes' => $this->minutes];
    }

    protected function subjectId(): int|string
    {
        return $this->idFor($this->alertId);
    }

    protected function pushType(): string
    {
        return $this->typeFor('duty_sos', 'sos');
    }

    private function targetUrl(): string
    {
        return $this->url ?? self::urlTo('admin.alertes', [], '/admin/alertes');
    }
}
