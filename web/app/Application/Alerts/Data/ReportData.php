<?php

declare(strict_types=1);

namespace App\Application\Alerts\Data;

use App\Application\Shared\StoredMedia;
use App\Enums\AlertSource;
use App\Enums\AlertType;
use App\Enums\ContactChannel;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Signalement ou besoin à créer (P5-02, Q11 a : une seule charge utile web et mobile). Les pièces jointes sont déjà
 * passées par MediaUploader (disque privé, métadonnées retirées) ; aucun champ n'est lu de la requête ici.
 */
final readonly class ReportData
{
    /**
     * @param  list<string>  $needs
     * @param  list<StoredMedia>  $attachments
     */
    public function __construct(
        public AlertType $type,
        public string $idempotencyKey,
        public AlertSource $source,
        public ?int $userId,
        public bool $anonymous,
        public ?string $category,
        public string $description,
        public ?CarbonImmutable $occurredOn,
        public array $needs,
        public ?int $zoneId,
        public ?int $communeId = null,
        public ?float $lat = null,
        public ?float $lng = null,
        public ?int $accuracy = null,
        public bool $contactable = false,
        public ?string $contact = null,
        public ?ContactChannel $preferredChannel = null,
        public array $attachments = [],
        public ?int $deviceId = null,
    ) {
        if ($type === AlertType::Sos) {
            throw new InvalidArgumentException('A SOS is created by TriggerSos (SosData), not CreateReport.');
        }
    }

    /** RG-01, RG-02 : sans compte, toujours anonyme ; avec compte, au choix de la personne. */
    public function isAnonymous(): bool
    {
        return $this->anonymous || $this->userId === null;
    }
}
