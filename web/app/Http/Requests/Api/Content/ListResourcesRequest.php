<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Content;

use App\Enums\ResourceKind;
use App\Http\Requests\Api\Content\Concerns\ContentListingRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /api/v1/ressources (P6-08, simulateur `ressources`) : `?q=`, `?type=` (ResourceKind hors `article`, les
 * actualités vivant sur /actualites, Q1), `?categorie=`, `?page=`, `?per_page=` (≤ xomiton.api.max_per_page).
 */
final class ListResourcesRequest extends FormRequest
{
    use ContentListingRequest;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->listingRules() + [
            'type' => ['sometimes', 'nullable', 'string', Rule::enum(ResourceKind::class)->except([ResourceKind::Article])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.(int) config('xomiton.api.max_per_page', 50)],
        ];
    }

    public function kind(): ?ResourceKind
    {
        $kind = $this->validated('type');

        return is_string($kind) && $kind !== '' ? ResourceKind::from($kind) : null;
    }

    public function page(): int
    {
        return max(1, (int) ($this->validated('page') ?? 1));
    }
}
