<?php

declare(strict_types=1);

namespace App\Http\Resources\Peer;

use App\Enums\CaseStatus;
use App\Models\CaseFollowup;
use App\Models\CaseRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dossier vu par un compte qui le voit (`CaseRecord::visibleTo`, P9-02) : champs de la collecte avec leurs libellés,
 * statut, code de registre. Les **notes** (chiffrées au repos) ne sont déchiffrées que dans le détail
 * (`withNotes()` : `GET pair/cases/{code}`, réponses de création et de modification), jamais dans la liste (ADR-33) ;
 * réponses `no-store`. Jamais l'identité de la personne qui a collecté (`mine` dit seulement si c'est soi).
 *
 * @property CaseRecord $resource
 */
final class CaseResource extends JsonResource
{
    private bool $withNotes = false;

    /** @var array<string, mixed>|null */
    private ?array $sheet = null;

    /**
     * Fiche de référence en cinq sections (CaseSheet), servie au seul détail `GET pair/cases/{code}` : la même que
     * celle de l'écran web du dossier (mêmes colonnes, mêmes exclusions : jamais l'auteur·e présumé·e des faits ni le
     * signataire du consentement).
     *
     * @param  array<string, mixed>  $sheet
     */
    public function withSheet(array $sheet): static
    {
        $this->sheet = $sheet;

        return $this;
    }

    public function withNotes(bool $withNotes = true): static
    {
        $this->withNotes = $withNotes;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $case = $this->resource;
        $viewer = $request->user();
        $locked = $case->isAnonymized() || $case->status === CaseStatus::Archived;

        return [
            'id' => (int) $case->getKey(),
            'code' => $case->code,
            'kind' => $case->kind->value,
            'kind_label' => $case->kind->label(),
            'occurred_on' => $case->occurred_on?->format('Y-m-d'),
            'zone' => $case->zone === null ? null : ['id' => (int) $case->zone->getKey(), 'name' => $case->zone->name],
            'commune' => $case->commune === null ? null : ['id' => (int) $case->commune->getKey(), 'name' => $case->commune->name],
            'population' => $case->population === null ? null : ['id' => (int) $case->population->getKey(), 'label' => $case->population->label],
            'sex' => $case->sex?->value,
            'sex_label' => $case->sex?->label(),
            'age_range' => $case->age_range?->value,
            'age_range_label' => $case->age_range?->label(),
            'violence_type' => $case->violenceType === null ? null : ['id' => (int) $case->violenceType->getKey(), 'label' => $case->violenceType->label],
            'referral' => $case->referral,
            'status' => $case->status->value,
            'status_label' => $case->status->label(),
            'status_badge' => $case->status->badge(),
            'source' => $case->source->value,
            'mine' => $viewer instanceof User && (int) $case->collected_by === (int) $viewer->getKey(),
            'client_uuid' => $this->when($viewer instanceof User && (int) $case->collected_by === (int) $viewer->getKey(), $case->client_uuid),
            'anonymized' => $case->isAnonymized(),
            'notes' => $this->when($this->withNotes, fn (): ?string => $case->isAnonymized() ? null : $case->notes),
            'followups_count' => $this->when($case->followups_count !== null, fn (): int => (int) $case->followups_count),
            'followups' => $this->when(
                $this->withNotes && $case->relationLoaded('followups'),
                fn (): array => CaseFollowupResource::collection($case->followups->sortBy(
                    static fn (CaseFollowup $followup): string => ($followup->date?->format('Y-m-d') ?? '').sprintf('%010d', (int) $followup->getKey()),
                )->values())->resolve($request),
            ),
            'sheet' => $this->when($this->sheet !== null, fn (): ?array => $this->sheet),
            'created_at' => $case->created_at?->toIso8601String(),
            'updated_at' => $case->updated_at?->toIso8601String(),
            'closed_at' => $case->closed_at?->toIso8601String(),
            'archived_at' => $case->archived_at?->toIso8601String(),
            'can' => [
                'update' => ! $locked && $viewer instanceof User && $viewer->can('update', $case),
                'add_followup' => ! $locked && $viewer instanceof User && $viewer->can('create', [CaseFollowup::class, $case]),
            ],
        ];
    }
}
