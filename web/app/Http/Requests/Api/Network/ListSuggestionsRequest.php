<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Network;

use App\Application\Community\Data\NetworkFilters;
use App\Domain\Community\NetworkRules;
use App\Http\Requests\Api\Network\Concerns\NetworkRequest;
use App\Models\Follow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET follows/suggestions?q=&zone=&role=&cursor=&per_page= (P8-06, who-to-follow.php l.97-104) : recherche ≤ 60 car. sur le
 * pseudonyme, zone active (id), rôle public `member` · `peer` · `team`. Valeur inconnue → 422 (la page web, elle, revient
 * au filtre par défaut).
 */
final class ListSuggestionsRequest extends FormRequest
{
    use NetworkRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('viewAny', Follow::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:'.(int) config('xomiton.network.search_max', 60)],
            'zone' => ['sometimes', 'nullable', 'integer', Rule::exists('zones', 'id')->where('is_active', true)],
            'role' => ['sometimes', 'nullable', 'string', Rule::in(NetworkRules::ROLES)],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:512'],
        ];
    }

    public function filters(): NetworkFilters
    {
        $q = $this->validated('q');
        $zone = $this->validated('zone');
        $role = $this->validated('role');

        return new NetworkFilters(
            q: is_string($q) && trim($q) !== '' ? trim($q) : null,
            zoneId: $zone !== null && $zone !== '' ? (int) $zone : null,
            role: is_string($role) && $role !== '' ? $role : null,
        );
    }
}
