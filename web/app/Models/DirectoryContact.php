<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DirectoryRole;
use Database\Factories\DirectoryContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Contact de l'annuaire d'orientation (DATABASE_DICTIONARY.md §5 `directory_contacts`) :
 * cible du routage des alertes (RG-04) et de l'escalade au point focal national (RG-05).
 */
class DirectoryContact extends Model
{
    /** @use HasFactory<DirectoryContactFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'role',
        'org',
        'phone',
        'email',
        'whatsapp',
        'zone_id',
        'commune_id',
        'availability',
        'is_active',
        'is_public',
        'is_national_focal',
        'user_id',
        'notes',
    ];

    /**
     * Les notes internes et le compte lié restent dans l'administration.
     *
     * @var list<string>
     */
    protected $hidden = [
        'notes',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => DirectoryRole::class,
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_national_focal' => 'boolean',
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
     * @return BelongsTo<Commune, $this>
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /**
     * Compte membre lié : voit les alertes « routées vers moi ».
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Alert, $this>
     */
    public function routedAlerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'routed_to');
    }

    /**
     * @return HasMany<AlertNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(AlertNotification::class);
    }

    /**
     * Escalades qui ont retiré une alerte à ce contact (RG-05).
     *
     * @return HasMany<AlertEscalation, $this>
     */
    public function escalationsFrom(): HasMany
    {
        return $this->hasMany(AlertEscalation::class, 'from_contact_id');
    }

    /**
     * Escalades qui ont confié une alerte à ce contact (point focal national le plus souvent).
     *
     * @return HasMany<AlertEscalation, $this>
     */
    public function escalationsTo(): HasMany
    {
        return $this->hasMany(AlertEscalation::class, 'to_contact_id');
    }

    /**
     * @param  Builder<DirectoryContact>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Visibles dans l'annuaire public et l'espace membre.
     *
     * @param  Builder<DirectoryContact>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true);
    }

    /**
     * @param  Builder<DirectoryContact>  $query
     */
    public function scopeWithRole(Builder $query, DirectoryRole $role): void
    {
        $query->where('role', $role);
    }

    /**
     * Contacts d'un département (null : contacts nationaux, sans zone).
     *
     * @param  Builder<DirectoryContact>  $query
     */
    public function scopeInZone(Builder $query, Zone|int|null $zone): void
    {
        $zone === null
            ? $query->whereNull('zone_id')
            : $query->where('zone_id', $zone instanceof Zone ? $zone->getKey() : $zone);
    }

    /**
     * Points focaux nationaux (destinataires de l'escalade RG-05).
     *
     * @param  Builder<DirectoryContact>  $query
     */
    public function scopeNationalFocal(Builder $query): void
    {
        $query->where('is_national_focal', true);
    }

    /**
     * Points focaux (nationaux ou de zone).
     *
     * @param  Builder<DirectoryContact>  $query
     */
    public function scopeFocal(Builder $query): void
    {
        $query->where('role', DirectoryRole::PointFocal);
    }

    /**
     * Contacts ayant un compte membre lié.
     *
     * @param  Builder<DirectoryContact>  $query
     */
    public function scopeLinkedTo(Builder $query, User|int $user): void
    {
        $query->where('user_id', $user instanceof User ? $user->getKey() : $user);
    }

    /**
     * @param  Builder<DirectoryContact>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('role')->orderBy('name');
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isNationalFocal(): bool
    {
        return $this->is_national_focal;
    }

    /** Peut être notifié : au moins un canal joignable. */
    public function isReachable(): bool
    {
        return $this->phone !== null || $this->email !== null || $this->whatsapp !== null || $this->user_id !== null;
    }
}
