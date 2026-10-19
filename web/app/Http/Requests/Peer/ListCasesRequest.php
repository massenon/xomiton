<?php

declare(strict_types=1);

namespace App\Http\Requests\Peer;

use App\Application\Cases\Queries\CaseFilters;
use App\Enums\CaseKind;
use App\Enums\CaseStatus;
use App\Models\CaseRecord;
use App\Support\ListingQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `GET pair/cases?status=&kind=&q=&page=&per_page=` (P9-02) : filtres validés (422 sur une valeur hors liste), `q`
 * cherche dans le code seulement (ADR-33).
 */
final class ListCasesRequest extends FormRequest
{
    public const SEARCH_MAX = 12;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CaseRecord::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(CaseStatus::class)],
            'kind' => ['nullable', Rule::enum(CaseKind::class)],
            'q' => ['nullable', 'string', 'max:'.self::SEARCH_MAX],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.max(1, (int) config('xomiton.api.max_per_page', 50))],
        ];
    }

    public function filters(): CaseFilters
    {
        $q = ListingQuery::search($this->validated('q'), self::SEARCH_MAX);

        return new CaseFilters(
            status: CaseStatus::tryFrom((string) $this->validated('status')),
            kind: CaseKind::tryFrom((string) $this->validated('kind')),
            code: $q === '' ? null : $q,
        );
    }

    public function page(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }
}
