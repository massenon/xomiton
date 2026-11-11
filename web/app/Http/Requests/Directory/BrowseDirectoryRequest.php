<?php

declare(strict_types=1);

namespace App\Http\Requests\Directory;

use App\Application\Directory\DirectoryFilters;
use App\Enums\DirectoryRole;
use App\Models\Commune;
use App\Models\User;
use App\Models\Zone;
use App\Support\ListingQuery;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtres de l'annuaire membre, page `/annuaire` et `GET /api/v1/directory` (P8-12 ; prototype annuaire.php l.30) :
 * lecture tolérante comme le prototype (GET sans Form Request) — recherche assainie et tronquée à
 * `xomiton.directory.search_max`, rôle (valeur de DirectoryRole), département (zone active), commune (de ce
 * département) ; une valeur inconnue est ignorée, jamais un 422. `page` : entier ≥ 1.
 */
final class BrowseDirectoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->viewerOrNull() instanceof User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function viewer(): User
    {
        $user = $this->viewerOrNull();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    public function filters(): DirectoryFilters
    {
        $q = ListingQuery::search($this->query('q'), (int) config('xomiton.directory.search_max', 80));
        $role = $this->query('role');
        $zoneId = $this->positiveInt('zone');
        $zone = $zoneId === null ? null : Zone::query()->active()->find($zoneId);
        $communeId = $zone === null ? null : $this->positiveInt('commune');
        $commune = $communeId === null ? null : Commune::query()->where('zone_id', $zone?->getKey())->find($communeId);

        return new DirectoryFilters(
            q: $q === '' ? null : $q,
            role: is_string($role) ? DirectoryRole::tryFrom($role) : null,
            zoneId: $zone === null ? null : (int) $zone->getKey(),
            communeId: $commune === null ? null : (int) $commune->getKey(),
        );
    }

    public function page(): int
    {
        return $this->positiveInt('page') ?? 1;
    }

    private function positiveInt(string $key): ?int
    {
        $value = $this->query($key);
        $int = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;

        return $int === false ? null : (int) $int;
    }

    private function viewerOrNull(): ?User
    {
        $user = $this->user() ?? $this->user('sanctum');

        return $user instanceof User ? $user : null;
    }
}
