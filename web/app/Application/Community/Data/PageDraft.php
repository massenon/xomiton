<?php

declare(strict_types=1);

namespace App\Application\Community\Data;

/**
 * Page à créer (POST /api/v1/pages, P8-03 ; create-page.php), validée par
 * App\Http\Requests\Api\Community\StorePageRequest : nom (3–80), catégorie de `xomiton.community.page_categories`,
 * description (≤ 400), site web facultatif.
 */
final readonly class PageDraft
{
    public function __construct(
        public string $name,
        public string $category,
        public ?string $description,
        public ?string $website,
    ) {}
}
