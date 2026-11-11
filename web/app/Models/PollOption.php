<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PollOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Option d'un sondage (libellé unique par sondage).
 */
class PollOption extends Model
{
    /** @use HasFactory<PollOptionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'poll_id',
        'label',
        'position',
        'votes_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'votes_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Poll, $this>
     */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    /**
     * @return HasMany<PollVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    /**
     * Part des votes du sondage (0–100), 0 si personne n'a voté.
     */
    public function percentage(): float
    {
        $total = (int) $this->poll->votes_count;

        return $total === 0 ? 0.0 : round($this->votes_count * 100 / $total, 1);
    }
}
