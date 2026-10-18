<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

/**
 * Charge utile d'une notification push (Web Push et FCM), NOTIFICATIONS_INSTRUCTION.md §4.1 et P15-08 : un titre,
 * un corps déjà neutralisé si la notification est sensible et que la personne masque le contenu, un lien d'ouverture
 * profonde et des données réduites à `type`, `id`, `url`, `category`. Aucun récit, aucune position, aucun contact.
 *
 * @phpstan-type PushData array{type: string, id: int|string|null, url: string|null, category: string}
 */
final readonly class PushMessage
{
    /**
     * @param  PushData  $data
     */
    public function __construct(
        public string $title,
        public string $body,
        public ?string $url,
        public array $data,
        public ?string $icon = null,
    ) {}

    public function isUrgent(): bool
    {
        return in_array($this->data['category'], ['sos', 'alertes'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'icon' => $this->icon,
            'data' => $this->data,
        ];
    }

    public function toJson(): string
    {
        return (string) json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
