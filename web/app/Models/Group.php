<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GroupPrivacy;
use App\Enums\MembershipStatus;
use App\Models\Concerns\HasUlid;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Groupe de la communauté (DATABASE_DICTIONARY.md §4 `groups`).
 */
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory, HasUlid, SoftDeletes;

    /** Couverture par défaut (prototype x_group_cover : `covers/gr-default.jpg`). */
    public const DEFAULT_COVER = 'assets/images/xomiton/covers/gr-default.jpg';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'name',
        'slug',
        'description',
        'privacy',
        'zone_id',
        'image_path',
        'cover_path',
        'owner_id',
        'members_count',
        'posts_count',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'privacy' => GroupPrivacy::class,
            'members_count' => 'integer',
            'posts_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<GroupMember, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    /**
     * Membres actifs (adhésion validée, non bannis).
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot(['role', 'status', 'joined_at'])
            ->wherePivot('status', MembershipStatus::Active->value)
            ->withTimestamps();
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @param  Builder<Group>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Group>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('privacy', GroupPrivacy::Public);
    }

    /**
     * Groupes nationaux (sans zone) ou rattachés au département donné.
     *
     * @param  Builder<Group>  $query
     */
    public function scopeForZone(Builder $query, ?int $zoneId): void
    {
        $query->where(function (Builder $q) use ($zoneId): void {
            $q->whereNull('zone_id');

            if ($zoneId !== null) {
                $q->orWhere('zone_id', $zoneId);
            }
        });
    }

    public function isPrivate(): bool
    {
        return $this->privacy === GroupPrivacy::Private;
    }

    public function isNational(): bool
    {
        return $this->zone_id === null;
    }
}
