<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GroupRole;
use App\Enums\MembershipStatus;
use Database\Factories\GroupMemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Adhésion d'un·e membre à un groupe (rôle et statut).
 */
class GroupMember extends Model
{
    /** @use HasFactory<GroupMemberFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'group_id',
        'user_id',
        'role',
        'status',
        'joined_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => GroupRole::class,
            'status' => MembershipStatus::class,
            'joined_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<GroupMember>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', MembershipStatus::Active);
    }

    /**
     * @param  Builder<GroupMember>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', MembershipStatus::Requested);
    }

    /**
     * Membres pouvant modérer le groupe (modérateur·rices et admins).
     *
     * @param  Builder<GroupMember>  $query
     */
    public function scopeModerators(Builder $query): void
    {
        $query->whereIn('role', [GroupRole::Moderator, GroupRole::Admin]);
    }

    public function isActive(): bool
    {
        return $this->status === MembershipStatus::Active;
    }

    public function canModerate(): bool
    {
        return $this->isActive() && $this->role->canModerate();
    }
}
