<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Content\Concerns;

use App\Support\ListingQuery;

/**
 * Commun aux listes publiques de l'API mobile (P6-08, `GET thematiques`, `GET ressources`) : routes du groupe public
 * (ADR-31), aucun compte requis ; `?categorie=` (slug `[a-z0-9-]{1,60}`, inconnu → 404 au contrôleur, comme le site),
 * `?q=` (100 caractères au plus, assaini par ListingQuery comme les pages publiques) ; noms de champs dans
 * lang/fr/mobile.php (`api.attributes`).
 */
trait ContentListingRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    protected function listingRules(): array
    {
        return [
            'categorie' => ['sometimes', 'nullable', 'string', 'regex:/^[a-z0-9-]{1,60}$/'],
            'q' => ['sometimes', 'nullable', 'string', 'max:'.ListingQuery::SEARCH_MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_map(
            static fn (string $key): string => (string) __('mobile.api.attributes.'.$key),
            array_combine(['categorie', 'q', 'type', 'page', 'per_page'], ['categorie', 'q', 'type', 'page', 'per_page']),
        );
    }

    public function categorySlug(): ?string
    {
        $slug = $this->validated('categorie');

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    public function search(): string
    {
        return ListingQuery::search($this->validated('q'));
    }
}
