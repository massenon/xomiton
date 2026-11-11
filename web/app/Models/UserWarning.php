<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\UserWarningFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avertissement adressé à un·e membre par la modération (DATABASE_DICTIONARY.md §7 `user_warnings`) :
 * journal immuable, sans `updated_at`, qui sert au compte des récidives avant suspension.
 */
class UserWarning extends Model
{
    /** @use HasFactory<UserWarningFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'issued_by',
        'reason',
        'moderation_report_id',
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
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return BelongsTo<ModerationReport, $this>
     */
    public function moderationReport(): BelongsTo
    {
        return $this->belongsTo(ModerationReport::class);
    }

    /**
     * @param  Builder<UserWarning>  $query
     */
    public function scopeForUser(Builder $query, User|int $user): void
    {
        $query->where('user_id', $user instanceof User ? $user->getKey() : $user);
    }

    /**
     * Avertissements émis depuis une date (récidives sur la période de référence).
     *
     * @param  Builder<UserWarning>  $query
     */
    public function scopeSince(Builder $query, CarbonInterface $since): void
    {
        $query->where('created_at', '>=', $since);
    }

    /**
     * @param  Builder<UserWarning>  $query
     */
    public function scopeChronological(Builder $query): void
    {
        $query->orderBy('created_at')->orderBy('id');
    }
}
