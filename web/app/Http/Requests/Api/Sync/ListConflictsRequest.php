<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Sync;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `GET sync/conflicts?status=open|resolved|all&per_page=` (P9-06, docs/API.md §21.4) : conflits de l'appareil.
 */
final class ListConflictsRequest extends FormRequest
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
            'status' => ['sometimes', 'string', 'in:open,resolved,all'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.(int) config('xomiton.api.max_per_page', 50)],
        ];
    }

    public function status(): string
    {
        return (string) ($this->validated('status') ?? 'all');
    }
}
