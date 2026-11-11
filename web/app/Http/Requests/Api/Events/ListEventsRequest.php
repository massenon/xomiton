<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Events;

use App\Application\Community\Events\EventDirectory;
use App\Http\Requests\Api\Events\Concerns\EventRequest;
use App\Models\Category;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET events?filter=upcoming|going|past&from=today&category=&q=&page=&per_page= (P8-04, events.php : puces de catégorie
 * et recherche ; simulateur.html l.1162 : `?from=today`, équivalent de `upcoming`). Catégorie : slug d'une catégorie
 * d'événement active (422 sinon) ; recherche ≤ 60 caractères.
 */
final class ListEventsRequest extends FormRequest
{
    use EventRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('viewAny', Event::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'nullable', 'string', Rule::in(EventDirectory::FILTERS)],
            'from' => ['sometimes', 'nullable', 'string', Rule::in(['today'])],
            'category' => ['sometimes', 'nullable', 'string', 'max:60', Rule::exists('categories', 'slug')->where('kind', Category::KIND_EVENT)->where('is_active', true)],
            'q' => ['sometimes', 'nullable', 'string', 'max:'.(int) config('xomiton.community.search_max', 60)],
        ];
    }

    public function filter(): string
    {
        $filter = $this->validated('filter');

        return is_string($filter) && $filter !== '' ? $filter : EventDirectory::FILTER_UPCOMING;
    }

    public function category(): ?string
    {
        $category = $this->validated('category');

        return is_string($category) && $category !== '' ? $category : null;
    }

    public function term(): ?string
    {
        $q = $this->validated('q');

        return is_string($q) && trim($q) !== '' ? trim($q) : null;
    }
}
