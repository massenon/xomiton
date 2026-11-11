<?php

declare(strict_types=1);

namespace App\Domain\Alerts\ValueObjects;

use App\Enums\RoutingFallback;

/**
 * Résultat du routage : contact retenu, niveau de repli (`alerts.routing_fallback`) et niveau numérique (1 à 4, 0 si
 * aucun contact ou attribution manuelle).
 */
final readonly class RoutingDecision
{
    public function __construct(
        public ?int $contactId,
        public ?RoutingFallback $fallback,
        public int $level,
    ) {}

    public static function none(): self
    {
        return new self(null, null, 0);
    }

    public static function at(RoutingFallback $fallback, int $contactId): self
    {
        return new self($contactId, $fallback, $fallback->level());
    }

    public static function manual(int $contactId): self
    {
        return new self($contactId, RoutingFallback::Manual, 0);
    }

    public function isRouted(): bool
    {
        return $this->contactId !== null;
    }

    /** Badge « repli » de l'aperçu admin : vrai pour focal_zone, focal_national, first_active. */
    public function isFallback(): bool
    {
        return $this->fallback?->isFallback() ?? false;
    }
}
