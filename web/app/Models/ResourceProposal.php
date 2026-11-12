<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProposalStatus;
use App\Enums\ResourceKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ressource proposée par un·e membre (P18-12 ; DATABASE_DICTIONARY.md `resource_proposals`) : jamais publique. La file de
 * validation de `/admin/contenu/propositions` l'accepte (ressource en brouillon créée dans /admin/contenu) ou la refuse
 * avec un motif écrit pour l'auteur·rice. Le fichier PDF vit sur le disque privé (MediaUploader, collection `documents`).
 */
class ResourceProposal extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'title',
        'kind',
        'summary',
        'category_id',
        'external_url',
        'file_path',
        'file_size',
        'credit_author',
        'status',
        'refusal_reason',
        'resource_id',
        'reviewed_by',
        'reviewed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'credit_author' => 'boolean',
            'file_size' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<\App\Models\Resource, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function proposalStatus(): ProposalStatus
    {
        return ProposalStatus::tryFrom((string) $this->getAttributes()['status']) ?? ProposalStatus::Proposed;
    }

    public function resourceKind(): ResourceKind
    {
        return ResourceKind::tryFrom((string) $this->getAttributes()['kind']) ?? ResourceKind::Link;
    }

    public function isPending(): bool
    {
        return $this->proposalStatus() === ProposalStatus::Proposed;
    }

    /** @param  Builder<ResourceProposal>  $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ProposalStatus::Proposed->value);
    }
}
