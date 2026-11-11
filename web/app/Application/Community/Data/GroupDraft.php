<?php

declare(strict_types=1);

namespace App\Application\Community\Data;

use App\Enums\GroupPrivacy;

/**
 * Groupe à créer (POST /api/v1/groups, P8-02 ; modale « Créer un groupe » de groups.php l.111-116), validé par
 * App\Http\Requests\Api\Community\StoreGroupRequest : nom (3–80), description (≤ 300), confidentialité, département
 * (null = national).
 */
final readonly class GroupDraft
{
    public function __construct(
        public string $name,
        public ?string $description,
        public GroupPrivacy $privacy,
        public ?int $zoneId,
    ) {}
}
