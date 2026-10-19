<?php

declare(strict_types=1);

namespace App\Application\Community\Data;

use Carbon\CarbonImmutable;

/**
 * Événement à créer (POST /api/v1/events, P8-04 ; modale « Créer un événement » de partials/modals.php l.166-190),
 * validé par App\Http\Requests\Api\Events\StoreEventRequest : titre, début (et fin facultative) déjà ramenés au fuseau
 * de l'application, ville ou zone, adresse exacte (réservée aux participant·es), en ligne et lien, catégorie, description,
 * groupe (ULID résolu en id par la requête) et ouverture aux visiteur·ses (modération seulement).
 */
final readonly class EventDraft
{
    public function __construct(
        public string $title,
        public CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
        public ?string $place,
        public ?string $address,
        public bool $isOnline,
        public ?string $onlineUrl,
        public ?int $categoryId,
        public ?string $description,
        public ?int $groupId,
        public bool $isPublic,
    ) {}
}
