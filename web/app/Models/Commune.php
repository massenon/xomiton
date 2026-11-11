<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CommuneFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Commune rattachée à un département ; le couple (zone_id, slug) est unique.
 */
class Commune extends Model
{
    /** @use HasFactory<CommuneFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'zone_id',
        'name',
        'slug',
        'position',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Zone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<DirectoryContact, $this> */
    public function directoryContacts(): HasMany
    {
        return $this->hasMany(DirectoryContact::class);
    }

    /** @return HasMany<Alert, $this> */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /** @return HasMany<CaseRecord, $this> */
    public function cases(): HasMany
    {
        return $this->hasMany(CaseRecord::class);
    }

    /** @param  Builder<Commune>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<Commune>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('name');
    }

    /**
     * Communes d'un département (règle `commune_in_zone` des Form Requests).
     *
     * @param  Builder<Commune>  $query
     */
    public function scopeInZone(Builder $query, Zone|int $zone): void
    {
        $query->where('zone_id', $zone instanceof Zone ? $zone->getKey() : $zone);
    }
}
