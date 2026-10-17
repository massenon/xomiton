<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PollFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sondage porté par une publication (RG-09 : 2 à 6 options).
 */
class Poll extends Model
{
    /** @use HasFactory<PollFactory> */
    use HasFactory;

    public const MIN_OPTIONS = 2;

    public const MAX_OPTIONS = 6;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'post_id',
        'question',
        'multiple',
        'results_before_vote',
        'ends_at',
        'votes_count',
        'voters_count',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'multiple' => 'boolean',
            'results_before_vote' => 'boolean',
            'ends_at' => 'datetime',
            'votes_count' => 'integer',
            'voters_count' => 'integer',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return HasMany<PollOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('position');
    }

    /**
     * @return HasMany<PollVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    /**
     * Sondages encore ouverts au vote : non clos et sans échéance passée.
     *
     * @param  Builder<Poll>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('closed_at')
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function isOpen(): bool
    {
        if ($this->closed_at !== null) {
            return false;
        }

        return $this->ends_at === null || $this->ends_at->isFuture();
    }

    public function hasVoted(User $user): bool
    {
        return $this->votes()->where('user_id', $user->id)->exists();
    }

    /**
     * Les résultats sont montrés avant le vote si l'auteur·rice l'a permis, après le vote, ou à la clôture.
     */
    public function canShowResultsTo(?User $user): bool
    {
        if ($this->results_before_vote || ! $this->isOpen()) {
            return true;
        }

        return $user !== null && $this->hasVoted($user);
    }
}
