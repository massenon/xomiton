<?php

declare(strict_types=1);

namespace App\Application\Cases;

use App\Application\Cases\Data\FollowupInput;
use App\Application\Cases\Results\FollowupWritten;
use App\Domain\Cases\Exceptions\CaseLocked;
use App\Domain\Cases\Exceptions\ClientUuidConflict;
use App\Domain\Cases\Exceptions\ClientUuidReused;
use App\Enums\CaseStatus;
use App\Models\CaseFollowup;
use App\Models\CaseRecord;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Ajout d'une étape de suivi à un dossier (P9-02, `POST pair/cases/{code}/followups` ; prototype admin/suivi.php
 * l.20-33) : autorisation `CaseFollowupPolicy::create` (le pair sur **ses** dossiers), dossier archivé ou anonymisé
 * refusé (422 `case_locked`), commentaire chiffré. **Idempotent par `client_uuid`** comme CreateCase (réutilisé par
 * `sync/push`, P9-06) : rejeu du même uuid sur le même dossier par la même personne avec la même saisie → l'étape
 * d'origine (200) ; autre saisie → 422 `idempotency_key_reused` ; uuid porté par une autre étape (autre dossier ou
 * autre personne) → 422 `client_uuid_conflict`. Audit « case_followups.created ».
 */
final class AddFollowup
{
    public function __invoke(User $by, CaseRecord $case, FollowupInput $input, ?string $clientUuid = null): FollowupWritten
    {
        Gate::forUser($by)->authorize('create', [CaseFollowup::class, $case]);

        $clientUuid = $clientUuid === null || trim($clientUuid) === '' ? null : Str::lower(trim($clientUuid));

        if ($clientUuid !== null && ($existing = $this->replay($by, $case, $input, $clientUuid)) !== null) {
            return new FollowupWritten($existing, created: false);
        }

        if ($case->isAnonymized() || $case->status === CaseStatus::Archived) {
            throw new CaseLocked;
        }

        try {
            $followup = CaseFollowup::query()->create($input->toAttributes() + [
                'case_id' => $case->getKey(),
                'by_user_id' => $by->getKey(),
                'client_uuid' => $clientUuid,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            $existing = $clientUuid === null ? null : $this->replay($by, $case, $input, $clientUuid);

            if ($existing === null) {
                throw $e;
            }

            return new FollowupWritten($existing, created: false);
        }

        Audit::log('case_followups.created', $followup, sprintf('dossier=%s ; étape=%s', $case->code, $followup->step), $by);

        return new FollowupWritten($followup, created: true);
    }

    private function replay(User $by, CaseRecord $case, FollowupInput $input, string $clientUuid): ?CaseFollowup
    {
        $existing = CaseFollowup::query()->where('client_uuid', $clientUuid)->first();

        if ($existing === null) {
            return null;
        }

        if ((int) $existing->case_id !== (int) $case->getKey() || (int) $existing->by_user_id !== (int) $by->getKey()) {
            throw new ClientUuidConflict;
        }

        if (! $input->matches($existing)) {
            throw new ClientUuidReused;
        }

        return $existing;
    }
}
