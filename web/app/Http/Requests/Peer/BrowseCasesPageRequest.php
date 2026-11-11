<?php

declare(strict_types=1);

namespace App\Http\Requests\Peer;

use App\Application\Cases\Queries\CaseFilters;
use App\Enums\CaseKind;
use App\Enums\CaseStatus;
use App\Models\CaseRecord;
use App\Models\User;
use App\Support\ListingQuery;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtres de la liste `/espace-pair/dossiers` (P9-08) : lecture tolérante comme les autres écrans Vue (une valeur
 * inconnue est ignorée, jamais un 422 sur un GET d'écran) ; mêmes filtres que `GET pair/cases` (statut, nature,
 * recherche dans le code seulement — ADR-33), la liste restant bornée par `CaseRecord::visibleTo`. `page` : entier ≥ 1.
 */
final class BrowseCasesPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CaseRecord::class) ?? false;
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
        $user = $this->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    public function filters(): CaseFilters
    {
        $status = $this->query('status');
        $kind = $this->query('kind');
        $q = ListingQuery::search($this->query('q'), ListCasesRequest::SEARCH_MAX);

        return new CaseFilters(
            status: is_string($status) ? CaseStatus::tryFrom($status) : null,
            kind: is_string($kind) ? CaseKind::tryFrom($kind) : null,
            code: $q === '' ? null : $q,
        );
    }

    public function page(): int
    {
        $page = $this->query('page');
        $int = is_string($page) ? filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;

        return $int === false ? 1 : (int) $int;
    }
}
