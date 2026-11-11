<?php

declare(strict_types=1);

namespace App\Application\Cases\Queries;

use App\Domain\Cases\CaseCode;
use App\Enums\CaseStatus;
use App\Models\CaseRecord;
use App\Models\User;
use App\Policies\Scopes\CaseRecordScope;
use App\Support\LikePattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dossiers visibles du compte (P9-02, `GET pair/cases`) : **toujours** par le scope `CaseRecord::visibleTo`
 * (ROLE_ET_PERMISSIONS.md §5 : un pair ne voit que les dossiers qu'il a collectés), les plus récents d'abord, par
 * page (liste d'administration, ARCH §4). Un dossier archivé sort de la liste sauf filtre `status=archive`
 * (P11-01). Recherche « contient » sur le code (`VBG-0042`, `0042`), jamais sur les notes chiffrées (ADR-33).
 */
final class ListCases
{
    /**
     * @return LengthAwarePaginator<int, CaseRecord>
     */
    public function page(User $viewer, CaseFilters $filters, int $perPage, int $page = 1): LengthAwarePaginator
    {
        return $this->query($viewer, $filters)
            ->with(['zone:id,name', 'commune:id,name', 'population:id,label', 'violenceType:id,label'])
            ->withCount('followups')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return Builder<CaseRecord>
     */
    public function query(User $viewer, CaseFilters $filters): Builder
    {
        $query = CaseRecordScope::visibleTo($viewer);

        $filters->status === null
            ? $query->where('status', '!=', CaseStatus::Archived->value)
            : $query->withStatus($filters->status);

        if ($filters->kind !== null) {
            $query->ofKind($filters->kind);
        }

        if ($filters->code !== null && $filters->code !== '') {
            $query->whereRaw(LikePattern::clause('code'), [LikePattern::contains(strtoupper($filters->code))]);
        }

        return $query;
    }

    /**
     * Dossier visible par son code (saisie tolérante, CaseCode::tryParse) ; null s'il n'existe pas **ou** n'est pas
     * visible : la réponse est la même (404 neutre, pas d'oracle sur les codes des autres).
     */
    public function find(User $viewer, string $code): ?CaseRecord
    {
        $parsed = CaseCode::tryParse($code);

        if ($parsed === null) {
            return null;
        }

        return CaseRecordScope::visibleTo($viewer)->where('code', $parsed->value())->first();
    }
}
