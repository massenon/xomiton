<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;

/**
 * Retour à l'auteur·e d'un SOS (P5-03) : « :name a bien reçu ton alerte et va t'appeler » quand une personne prévenue
 * accuse réception (compte ou lien signé). Cloche et push seulement ; catégorie SOS, sensible. Registre « tu »
 * (parcours de crise, VOICE_AND_TONE §3). `byLabel` est le libellé public de la personne (jamais un nom d'admin).
 */
final class SosAcknowledged extends XomitonNotification
{
    public function __construct(
        public readonly int $alertId,
        public readonly string $code,
        public readonly string $byLabel,
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
     * @return array{title: string, body: string, url: string, icon: string}
     */
    protected function content(object $notifiable): array
    {
        return [
            'title' => (string) __('notifications.sos_acknowledged.title'),
            'body' => (string) __('notifications.sos_acknowledged.body', ['name' => $this->byLabel]),
            'url' => $this->url ?? self::urlTo('sos', [], '/sos'),
            'icon' => 'check2-circle',
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.sos_acknowledged.discreet');
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
