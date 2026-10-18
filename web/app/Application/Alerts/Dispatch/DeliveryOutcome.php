<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch;

use App\Domain\Notifications\DeliveryResult;

/**
 * Résultat d'une remise externe : non tentée (budget ou délai du fournisseur épuisé) ou tentée, avec le résultat du
 * pilote et le nom du fournisseur effectif consigné dans `alert_notifications.provider`.
 */
final readonly class DeliveryOutcome
{
    public function __construct(
        public PlannedDelivery $delivery,
        public bool $attempted,
        public ?DeliveryResult $result,
        public string $provider,
    ) {}

    public static function notAttempted(PlannedDelivery $delivery, string $provider): self
    {
        return new self($delivery, false, null, $provider);
    }

    public function ok(): bool
    {
        return $this->attempted && $this->result?->ok === true;
    }

    public function gone(): bool
    {
        return $this->attempted && $this->result?->gone === true;
    }

    /** Message d'erreur masqué (aucun numéro), 255 caractères au plus. */
    public function error(): string
    {
        return DeliveryError::mask((string) ($this->result?->error ?? 'Échec de remise'));
    }
}
