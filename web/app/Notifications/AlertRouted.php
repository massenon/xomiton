<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;
use App\Support\LocaleFormatter;
use DateTimeInterface;

/**
 * « Alerte routée » (NOTIFICATIONS §2) : contact de l'annuaire ciblé et admin de garde ; cloche, push, e-mail
 * `alerte-routee`. Sensible : le type de violence et la zone ne s'affichent pas sur écran verrouillé en mode discret.
 * Registre « vous » (destinataire de l'équipe).
 */
final class AlertRouted extends XomitonNotification
{
    public function __construct(
        public readonly int $alertId,
        public readonly string $reference,
        public readonly string $typeLabel,
        public readonly string $zoneLabel,
        public readonly DateTimeInterface|string $receivedAt,
        public readonly ?string $url = null,
        // Rappel à 24 h sans accusé (Q27, P5-03) : titre « Rappel : … », contexte e-mail `alerte_rappel`.
        public readonly bool $reminder = false,
    ) {}

    public function category(): string
    {
        return DatabaseNotification::CATEGORY_ALERTS;
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
        return $this->reminder ? 'alerte_rappel' : 'alerte_routee';
    }

    /**
     * @return array<string, scalar|\Stringable|null>
     */
    protected function mailVariables(object $notifiable): array
    {
        return [
            'alert_reference' => $this->reference,
            'alert_zone' => $this->zoneLabel,
        ];
    }

    /**
     * @return array{title: string, body: string, url: string, icon: string}
     */
    protected function content(object $notifiable): array
    {
        return [
            'title' => (string) __($this->reminder ? 'notifications.alert_routed.reminder_title' : 'notifications.alert_routed.title'),
            'body' => (string) __('notifications.alert_routed.body', [
                'type' => $this->typeLabel,
                'zone' => $this->zoneLabel,
                'time' => LocaleFormatter::make()->time($this->receivedAt),
            ]),
            'url' => $this->url ?? self::urlTo('espace-pair', [], '/espace-pair'),
            'icon' => 'bell-fill',
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.alert_routed.discreet');
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
