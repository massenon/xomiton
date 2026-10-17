<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FollowFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Abonnement d'un·e membre (follower) à un·e autre (followed).
 */
class Follow extends Model
{
    /** @use HasFactory<FollowFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'follower_id',
        'followed_id',
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
     * @return BelongsTo<User, $this>
     */
    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function followed(): BelongsTo
    {
        return $this->belongsTo(User::class, 'followed_id');
    }

    /**
     * @param  Builder<Follow>  $query
     */
    public function scopeBetween(Builder $query, int $followerId, int $followedId): void
    {
        $query->where('follower_id', $followerId)->where('followed_id', $followedId);
    }

    /**
     * Abonnés d'un·e membre.
     *
     * @param  Builder<Follow>  $query
     */
    public function scopeFollowersOf(Builder $query, int $userId): void
    {
        $query->where('followed_id', $userId);
    }
}
