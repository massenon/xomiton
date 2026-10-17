<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FollowupOutcome;
use Database\Factories\CaseFollowupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Étape de suivi d'un dossier (accueil, écoute, appui juridique, suivi à 30 jours…).
 * Le commentaire est chiffré ; `next_on` alimente les rappels de la pair·e.
 */
class CaseFollowup extends Model
{
    /** @use HasFactory<CaseFollowupFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'case_id',
        'date',
        'step',
        'by_user_id',
        'outcome',
        'next_on',
        'comment',
        'client_uuid',
    ];

    /** @var list<string> */
    protected $hidden = [
        'comment',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'outcome' => FollowupOutcome::class,
            'next_on' => 'date',
            'comment' => 'encrypted',
        ];
    }

    /** @return BelongsTo<CaseRecord, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'by_user_id');
    }

    /** @param  Builder<CaseFollowup>  $query */
    public function scopeForCase(Builder $query, CaseRecord|int $case): void
    {
        $query->where('case_id', $case instanceof CaseRecord ? $case->getKey() : $case);
    }

    /**
     * Étapes planifiées ou reportées, à faire.
     *
     * @param  Builder<CaseFollowup>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereIn('outcome', [FollowupOutcome::Planned->value, FollowupOutcome::Postponed->value]);
    }

    /**
     * Étapes en attente dont la prochaine échéance est dépassée (rappels).
     *
     * @param  Builder<CaseFollowup>  $query
     */
    public function scopeOverdue(Builder $query, ?string $today = null): void
    {
        $query->pending()->whereNotNull('next_on')->where('next_on', '<', $today ?? now()->toDateString());
    }

    /** @param  Builder<CaseFollowup>  $query */
    public function scopeChronological(Builder $query): void
    {
        $query->orderBy('date')->orderBy('id');
    }

    public function isPending(): bool
    {
        return $this->outcome->isPending();
    }
}
