<?php

declare(strict_types=1);

namespace App\Http\Requests\Member;

use App\Models\User;
use App\Support\ListingQuery;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pages `/evenements` et `/evenements/{ulid}` (P8-04), servies aux membres (Vue) comme aux visiteur·ses (Blade publique,
 * Q35 a) : lecture seule et tolérante, comme le prototype — un onglet, une catégorie ou une recherche inconnus ou trop
 * longs reviennent à la valeur par défaut (l'API, elle, répond 422). La page publique garde sa règle d'URL propre : une
 * catégorie mal formée y est un 404 (`publicCategory()`, comme `/actualites`).
 */
final class EventsPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function viewer(): ?User
    {
        $user = $this->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * @param  list<string>  $allowed
     */
    public function choice(string $key, array $allowed, string $default): string
    {
        $value = $this->query($key);

        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    public function term(): ?string
    {
        $value = ListingQuery::search($this->query('q'), (int) config('xomiton.community.search_max', 60));

        return $value === '' ? null : $value;
    }

    /**
     * Slug de catégorie (`?cat=`) parmi ceux proposés, sinon null.
     *
     * @param  list<string>  $known
     */
    public function category(array $known): ?string
    {
        $value = $this->query('cat');

        return is_string($value) && in_array($value, $known, true) ? $value : null;
    }

    /** `?cat=` de la page publique : null (absent), false (mal formé → 404) ou le slug. */
    public function publicCategory(): string|false|null
    {
        return ListingQuery::slug($this->query('cat'));
    }
}
