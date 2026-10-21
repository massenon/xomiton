<?php

declare(strict_types=1);

namespace App\Application\Cases;

use App\Domain\Cases\Exceptions\CaseLocked;
use App\Enums\CaseStatus;
use App\Models\CaseRecord;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Archivage d'un dossier par l'administration (P11-01, « suppression logique » : un dossier ne se supprime jamais,
 * il s'archive puis s'anonymise, DATABASE_DICTIONARY.md §6). Autorisation : `CaseRecordPolicy::delete`
 * (`cases.view_all`). Depuis n'importe quel statut actif : le dossier est clos au passage (`closed_at` posé s'il ne
 * l'était pas), puis `archive` + `archived_at`. Déjà archivé ou anonymisé : 422 `case_locked`. Un dossier archivé sort
 * des listes et des indicateurs ; RestoreCase le rétablit. Journal d'audit « cases.archived » (code et statut d'origine,
 * jamais le contenu).
 */
final class ArchiveCase
{
    public function __invoke(User $by, CaseRecord $case): CaseRecord
    {
        Gate::forUser($by)->authorize('delete', $case);

        /** @var array{0: CaseRecord, 1: CaseStatus} $outcome */
        $outcome = DB::transaction(static function () use ($case): array {
            /** @var CaseRecord $fresh */
            $fresh = CaseRecord::query()->whereKey($case->getKey())->lockForUpdate()->firstOrFail();

            if ($fresh->isAnonymized() || $fresh->status === CaseStatus::Archived) {
                throw new CaseLocked;
            }

            $from = $fresh->status;
            $fresh->status = CaseStatus::Archived;
            $fresh->closed_at ??= now();
            $fresh->archived_at = now();
            $fresh->save();

            return [$fresh, $from];
        });

        [$fresh, $from] = $outcome;
        Audit::log('cases.archived', $fresh, sprintf('code=%s ; statut=%s→%s', $fresh->code, $from->value, CaseStatus::Archived->value), $by);

        return $fresh;
    }
}
