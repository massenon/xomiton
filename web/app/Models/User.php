<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * Image affichée quand le profil n'a pas d'avatar téléversé.
     */
    public const DEFAULT_AVATAR = 'assets/images/xomiton/avatar1.png';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'pseudo',
        'name',
        'email',
        'phone',
        'password',
        'status',
        'zone_id',
        'commune_id',
        'email_verified_at',
        'last_login_at',
        'last_seen_at',
        'suspended_at',
        'suspension_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        // name et phone ne sont jamais publics (RG-02) : seul le pseudo est affiché.
        'name',
        'phone',
        'password',
        'remember_token',
        'suspension_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'suspended_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasOne<Profile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * @return HasOne<UserSetting, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * @return BelongsTo<Commune, $this>
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /**
     * @return HasMany<Consent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /**
     * Blocages émis par ce compte.
     *
     * @return HasMany<UserBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(UserBlock::class);
    }

    /**
     * Comptes bloqués par ce compte.
     *
     * @return BelongsToMany<User, $this>
     */
    public function blockedUsers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'user_blocks', 'user_id', 'blocked_user_id')
            ->withPivot(['reason', 'created_at']);
    }

    /**
     * Comptes qui ont bloqué ce compte.
     *
     * @return BelongsToMany<User, $this>
     */
    public function blockedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'user_blocks', 'blocked_user_id', 'user_id')
            ->withPivot(['reason', 'created_at']);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', UserStatus::Active);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', UserStatus::Pending);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeSuspended(Builder $query): void
    {
        $query->where('status', UserStatus::Suspended);
    }

    /**
     * Comptes d'une zone (département) donnée.
     *
     * @param  Builder<User>  $query
     */
    public function scopeInZone(Builder $query, int $zoneId): void
    {
        $query->where('zone_id', $zoneId);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isSuspended(): bool
    {
        return $this->status === UserStatus::Suspended;
    }

    /**
     * URL de l'avatar : fichier du profil (disque public) ou image par défaut.
     *
     * @return Attribute<string, never>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $path = $this->profile?->avatar_path;

            if ($path === null || $path === '') {
                return asset(self::DEFAULT_AVATAR);
            }

            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }

            return asset('storage/'.ltrim($path, '/'));
        });
    }
}
