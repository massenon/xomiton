<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserBlockFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBlock extends Model
{
    /** @use HasFactory<UserBlockFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'blocked_user_id',
        'reason',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function blockedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_user_id');
    }

    /**
     * Blocages dans un sens ou dans l'autre entre deux comptes (filtrage du fil et du chat).
     *
     * @param  Builder<UserBlock>  $query
     */
    public function scopeBetween(Builder $query, int $userId, int $otherUserId): void
    {
        $query->where(function (Builder $q) use ($userId, $otherUserId): void {
            $q->where(['user_id' => $userId, 'blocked_user_id' => $otherUserId])
                ->orWhere(fn (Builder $r) => $r->where(['user_id' => $otherUserId, 'blocked_user_id' => $userId]));
        });
    }
}
