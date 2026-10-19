<?php

declare(strict_types=1);

namespace App\Application\Cases;

use App\Application\Cases\Data\CaseChanges;
use App\Application\Cases\Results\CaseWritten;
use App\Domain\Cases\Exceptions\CaseLocked;
use App\Domain\Cases\Exceptions\InvalidCaseTransition;
use App\Enums\CaseStatus;
use App\Models\CaseRecord;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Modification d'un dossier (P9-02, `PATCH pair/cases/{code}`) : champs de la collecte et statut, la nature et le
 * code ne changent jamais (RG-06). Autorisation : `CaseRecordPolicy::update` (le pair sur **ses** dossiers,
 * `cases.update_own`). Statut : transitions fermées de CaseStatus (422 `invalid_transition`) ; `clos` pose
 * `closed_at`, une réouverture l'efface ; `archive` (administration seulement, `cases.view_all`) pose `archived_at`.
 * Dossier archivé ou anonymisé : lecture seule (422 `case_locked`). Pas de suppression : un dossier se clôt.
 * Journal d'audit « cases.updated » (champs modifiés et statut, jamais leur contenu).
 */
final class UpdateCase
{
    public function __construct(private readonly CaseQuality $quality) {}

    public function __invoke(User $by, CaseRecord $case, CaseChanges $changes): CaseWritten
    {
        Gate::forUser($by)->authorize('update', $case);

        if ($changes->status === CaseStatus::Archived && ! $by->can('cases.view_all')) {
            throw new AuthorizationException;
        }

        /** @var array{0: CaseRecord, 1: list<string>, 2: CaseStatus, 3: CaseStatus} $outcome */
        $outcome = DB::transaction(static function () use ($case, $changes): array {
            /** @var CaseRecord $fresh */
            $fresh = CaseRecord::query()->whereKey($case->getKey())->lockForUpdate()->firstOrFail();

            if ($fresh->isAnonymized() || $fresh->status === CaseStatus::Archived) {
                throw new CaseLocked;
            }

            $from = $fresh->status;
            $fresh->fill($changes->attributes);

            if ($changes->status !== null && $changes->status !== $from) {
                if (! $from->canTransitionTo($changes->status)) {
                    throw new InvalidCaseTransition($from, $changes->status);
                }

                $fresh->status = $changes->status;
                $fresh->closed_at = match ($changes->status) {
                    CaseStatus::Closed => now(),
                    CaseStatus::Archived => $fresh->closed_at ?? now(),
                    default => null,
                };
                $fresh->archived_at = $changes->status === CaseStatus::Archived ? now() : null;
            }

            $dirty = array_keys($fresh->getDirty());
            $fresh->save();

            return [$fresh, $dirty, $from, $fresh->status];
        });

        [$fresh, $dirty, $from, $to] = $outcome;
        $fields = array_values(array_intersect(CaseChanges::FIELDS, $dirty));

        if ($fields !== [] || $from !== $to) {
            Audit::log('cases.updated', $fresh, sprintf(
                'code=%s ; champs=%s%s',
                $fresh->code,
                $fields === [] ? '-' : implode(',', $fields),
                $from !== $to ? sprintf(' ; statut=%s→%s', $from->value, $to->value) : '',
            ), $by);
        }

        return new CaseWritten($fresh, created: false, warnings: $this->quality->warnings($fresh, $by));
    }
}
