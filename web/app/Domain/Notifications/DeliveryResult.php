<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

/**
 * Résultat d'une remise push ou SMS : réussie, échouée (rejouable) ou définitivement impossible (`gone` : abonnement
 * 404 / 410 ou jeton FCM désinscrit → le canal supprime la ligne `push_subscriptions`).
 */
final readonly class DeliveryResult
{
    private function __construct(
        public bool $ok,
        public bool $gone,
        public ?string $error,
    ) {}

    public static function sent(): self
    {
        return new self(true, false, null);
    }

    public static function failed(string $error): self
    {
        return new self(false, false, $error);
    }

    public static function gone(?string $error = null): self
    {
        return new self(false, true, $error);
    }
}
