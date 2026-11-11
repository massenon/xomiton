<?php

declare(strict_types=1);

namespace App\Application\Sync\Pull\Collections;

use App\Application\Cases\PeerSpace;
use App\Application\Sync\Pull\EloquentSyncCollection;
use App\Enums\CaseStatus;
use App\Models\CaseRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * `cases` : dossiers collectés par le compte (espace pair actif, PeerSpace RG-03, et `cases.create` ; portée « les
 * siens » même pour l'administration : un appareil ne garde jamais tous les dossiers), ni archivés ni anonymisés. Archivé, anonymisé ou réattribué (pierre
 * tombale TombstoneRecorder) : retiré de l'appareil. Notes déchiffrées : elles viennent de l'appareil du compte.
 */
final class CasesCollection extends EloquentSyncCollection
{
    public const NAME = 'cases';

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
        return CaseRecord::class;
    }

    protected function candidates(User $user): Builder
    {
        return CaseRecord::query()->collectedBy($user);
    }

    protected function visible(User $user): Builder
    {
        return CaseRecord::query()->collectedBy($user)->whereNull('anonymized_at')->where('status', '!=', CaseStatus::Archived->value);
    }

    public function payload(Model $row, Request $request): array
    {
        /** @var CaseRecord $row */
        return self::row($row);
    }

    /**
     * Contenu d'un dossier sur l'appareil (aussi rendu par `sync/push` après une modification, champ `case`).
     *
     * @return array<string, mixed>
     */
    public static function row(CaseRecord $row): array
    {
        return [
            'id' => (int) $row->getKey(),
            'code' => (string) $row->code,
            'kind' => $row->kind->value,
            'occurred_on' => $row->occurred_on?->format('Y-m-d'),
            'zone_id' => $row->zone_id === null ? null : (int) $row->zone_id,
            'commune_id' => $row->commune_id === null ? null : (int) $row->commune_id,
            'population_id' => $row->population_id === null ? null : (int) $row->population_id,
            'sex' => $row->sex?->value,
            'age_range' => $row->age_range?->value,
            'violence_type_id' => $row->violence_type_id === null ? null : (int) $row->violence_type_id,
            'status' => $row->status->value,
            'referral' => $row->referral,
            'notes' => $row->notes,
            'client_uuid' => $row->client_uuid,
            'closed_at' => $row->closed_at?->toIso8601String(),
            'created_at' => $row->created_at?->toIso8601String(),
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
    }
}
