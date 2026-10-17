<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ZoneFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Département (référentiel géographique de premier niveau).
 */
class Zone extends Model
{
    /** @use HasFactory<ZoneFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
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

    /** @return HasMany<Commune, $this> */
    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class);
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

    /** @return HasMany<Group, $this> */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    /** @return HasMany<IndicatorValue, $this> */
    public function indicatorValues(): HasMany
    {
        return $this->hasMany(IndicatorValue::class);
    }

    /** @param  Builder<Zone>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<Zone>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('name');
    }
}
