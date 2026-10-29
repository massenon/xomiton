<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invitation à rejoindre l'équipe (table `role_invitations`) : code à usage unique créé par l'administration, jamais
 * conservé en clair (`code_hash`, SHA-256 ; `code_hint` = 4 derniers caractères pour la reconnaître dans la liste). La
 * personne invitée termine une inscription **membre** ordinaire et reçoit le rôle à la validation de son compte.
 */
class RoleInvitation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_REDEEMED = 'redeemed';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REFUSED = 'refused';

    public const STATUS_REVOKED = 'revoked';

    /** @var list<string> */
    protected $fillable = [
        'role', 'code_hash', 'code_hint', 'email', 'note', 'status', 'invited_by', 'user_id', 'expires_at',
        'redeemed_at', 'decided_at', 'decided_by', 'decision_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'redeemed_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hash(string $code): string
    {
        return hash('sha256', self::normalize($code));
    }

    /** Code saisi tel qu'il est lu : casse et séparateurs ignorés (`xm-ab12-cd34`). */
    public static function normalize(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    /**
     * @param  Builder<RoleInvitation>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING)->where('expires_at', '>', now());
    }

    /**
     * @param  Builder<RoleInvitation>  $query
     */
    public function scopeWaitingValidation(Builder $query): void
    {
        $query->where('status', self::STATUS_REDEEMED);
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->expires_at->isPast();
    }
}
