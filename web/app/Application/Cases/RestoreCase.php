<?php

declare(strict_types=1);

namespace App\Application\Cases;

use App\Application\Push\PushToPeers;
use App\Domain\Cases\Exceptions\CaseLocked;
use App\Domain\Cases\Exceptions\InvalidCaseTransition;
use App\Enums\CaseStatus;
use App\Models\CaseRecord;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Rétablissement d'un dossier archivé par l'administration (P11-01 : « reste restaurable ») : l'archive redevient
 * `clos` (seul statut qui précède l'archivage dans CaseStatus::allowedTransitions), `archived_at` effacé, `closed_at`
 * conservé. Un dossier anonymisé ne se rétablit pas (ses données sont effacées) ; un dossier non archivé : InvalidCaseTransition
 * (422 `invalid_transition`). Autorisation : `CaseRecordPolicy::delete`.
 * Journal d'audit « cases.restored ».
 */
final class RestoreCase
{
    public function __invoke(User $by, CaseRecord $case): CaseRecord
    {
        Gate::forUser($by)->authorize('delete', $case);

        /** @var CaseRecord $restored */
        $restored = DB::transaction(static function () use ($case): CaseRecord {
            /** @var CaseRecord $fresh */
            $fresh = CaseRecord::query()->whereKey($case->getKey())->lockForUpdate()->firstOrFail();

            if ($fresh->isAnonymized()) {
                throw new CaseLocked;
            }

            if ($fresh->status !== CaseStatus::Archived) {
                throw new InvalidCaseTransition($fresh->status, CaseStatus::Closed);
            }

            $fresh->status = CaseStatus::Closed;
            $fresh->archived_at = null;
            $fresh->closed_at ??= now();
            $fresh->save();

            return $fresh;
        });

        Audit::log('cases.restored', $restored, sprintf('code=%s ; statut=%s→%s', $restored->code, CaseStatus::Archived->value, CaseStatus::Closed->value), $by);
        app(PushToPeers::class)->caseChangedBy($restored, $by);

        return $restored;
    }
}
