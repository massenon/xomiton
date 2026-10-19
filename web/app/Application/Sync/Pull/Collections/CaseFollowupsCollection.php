<?php

declare(strict_types=1);

namespace App\Application\Sync\Pull\Collections;

use App\Application\Cases\PeerSpace;
use App\Application\Sync\Pull\EloquentSyncCollection;
use App\Enums\CaseStatus;
use App\Models\CaseFollowup;
use App\Models\CaseRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * `case_followups` : étapes de suivi des dossiers de la collection `cases` (même portée) ; suivi d'un dossier archivé,
 * anonymisé ou réattribué : retiré de l'appareil.
 */
final class CaseFollowupsCollection extends EloquentSyncCollection
{
    public const NAME = 'case_followups';

    public function name(): string
    {
        return self::NAME;
    }

    public function availableTo(User $user): bool
    {
        return PeerSpace::allows($user) && $user->can('cases.create');
    }

    protected function model(): string
    {
        return CaseFollowup::class;
    }

    protected function candidates(User $user): Builder
    {
        return CaseFollowup::query()->whereIn('case_id', CaseRecord::query()->select('id')->collectedBy($user));
    }

    protected function visible(User $user): Builder
    {
        return CaseFollowup::query()->whereIn('case_id', CaseRecord::query()->select('id')->collectedBy($user)
            ->whereNull('anonymized_at')->where('status', '!=', CaseStatus::Archived->value));
    }

    public function payload(Model $row, Request $request): array
    {
        /** @var CaseFollowup $row */
        return [
            'id' => (int) $row->getKey(),
            'case_id' => (int) $row->case_id,
            'date' => $row->date?->format('Y-m-d'),
            'step' => (string) $row->step,
            'outcome' => $row->outcome?->value,
            'next_on' => $row->next_on?->format('Y-m-d'),
            'comment' => $row->comment,
            'client_uuid' => $row->client_uuid,
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
    }
}
