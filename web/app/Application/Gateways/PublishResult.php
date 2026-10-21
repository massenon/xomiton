<?php

declare(strict_types=1);

namespace App\Application\Gateways;

use App\Enums\GatewayDeliveryStatus;

/**
 * Résultat d'une tentative de publication par un SocialPublisher (ADR-15) : état de la remise, identifiant et adresse
 * de la publication chez le réseau (connecteur réel), message d'erreur (jamais de jeton) et, pour la publication
 * manuelle assistée, le texte prêt à copier.
 */
final readonly class PublishResult
{
    public function __construct(
        public GatewayDeliveryStatus $status,
        public ?string $externalId = null,
        public ?string $externalUrl = null,
        public ?string $error = null,
        public ?string $copyText = null,
    ) {}

    /** Rien n'est parti : une personne publie à la main puis marque la remise (statut `manuel`). */
    public static function awaitingManual(string $copyText): self
    {
        return new self(GatewayDeliveryStatus::Pending, copyText: $copyText);
    }

    public static function published(string $externalId, ?string $externalUrl = null): self
    {
        return new self(GatewayDeliveryStatus::Published, $externalId, $externalUrl);
    }

    public static function failed(string $error): self
    {
        return new self(GatewayDeliveryStatus::Failed, error: $error);
    }
}
