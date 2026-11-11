<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PollVoteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vote individuel : jamais exposé par l'API, seuls les compteurs sortent.
 */
class PollVote extends Model
{
    /** @use HasFactory<PollVoteFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'poll_option_id',
        'poll_id',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PollOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(PollOption::class, 'poll_option_id');
    }

    /**
     * @return BelongsTo<Poll, $this>
     */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Votes d'un·e membre dans un sondage (contrôle « une option par (sondage, membre) » en choix unique).
     *
     * @param  Builder<PollVote>  $query
     */
    public function scopeForVoter(Builder $query, Poll $poll, User $user): void
    {
        $query->where('poll_id', $poll->id)->where('user_id', $user->id);
    }
}
