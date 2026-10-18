<?php

declare(strict_types=1);

namespace App\Application\Alerts\Data;

use App\Enums\AlertSource;

/**
 * SOS à créer au terme du compte à rebours local (Q39). Aucun champ n'est obligatoire (RG-01 : un SOS part toujours) ;
 * la position est ignorée si `shareLocation === false`.
 */
final readonly class SosData
{
    /**
     * @param  list<string>  $needs  besoins facultatifs, ajoutés à `securite`
     */
    public function __construct(
        public string $idempotencyKey,
        public AlertSource $source,
        public ?int $userId = null,
        public ?bool $shareLocation = null,
        public ?float $lat = null,
        public ?float $lng = null,
        public ?int $accuracy = null,
        public ?int $zoneId = null,
        public ?int $communeId = null,
        public ?string $message = null,
        public ?string $phone = null,
        public array $needs = [],
        public ?int $deviceId = null,
    ) {}

    public function keepsLocation(): bool
    {
        return $this->shareLocation !== false && $this->lat !== null && $this->lng !== null;
    }
}
