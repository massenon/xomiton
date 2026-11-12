<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerifiedBadge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Badge vérifié par l'administration (P18-09, DATABASE_DICTIONARY.md `user_badges`) : attribué à la main par un compte
 * `badges.manage` (App\Application\Admin\Users\ManageBadges), jamais par une règle automatique.
 */
class UserBadge extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'badge',
        'granted_by',
        'granted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'badge' => VerifiedBadge::class,
            'granted_by' => 'integer',
            'granted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Badge typé, d'après la valeur brute (lecture sûre pour l'analyse statique). */
    public function kind(): ?VerifiedBadge
    {
        return VerifiedBadge::tryFrom((string) $this->getRawOriginal('badge'));
    }
}
