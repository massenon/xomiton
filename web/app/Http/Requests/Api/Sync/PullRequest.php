<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Sync;

use App\Application\Sync\Pull\PullChanges;
use App\Application\Sync\Pull\PullCursor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `GET sync/pull?since=&collections[]=` (P9-06, docs/API.md §21.3) : `since` = jeton `meta.cursor` du tirage précédent
 * (absent : tirage complet), `collections[]` = sous-ensemble des collections du compte (absent : toutes).
 */
final class PullRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'since' => ['sometimes', 'nullable', 'string', 'max:'.PullCursor::MAX_LENGTH],
            'collections' => ['sometimes', 'array'],
            'collections.*' => ['string', Rule::in(PullChanges::names())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'since' => (string) __('sync.fields.since'),
            'collections' => (string) __('sync.fields.collections'),
            'collections.*' => (string) __('sync.fields.collection'),
        ];
    }

    public function since(): ?string
    {
        $since = $this->validated('since');

        return is_string($since) && $since !== '' ? $since : null;
    }

    /**
     * @return list<string>|null
     */
    public function collections(): ?array
    {
        $collections = $this->validated('collections');

        return is_array($collections) ? array_values(array_unique(array_map('strval', $collections))) : null;
    }
}
