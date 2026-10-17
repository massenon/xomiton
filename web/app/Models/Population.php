<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PopulationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Population clé suivie dans les dossiers VBG / VDH.
 */
class Population extends Model
{
    /** @use HasFactory<PopulationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'label',
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

    /** @return HasMany<CaseRecord, $this> */
    public function cases(): HasMany
    {
        return $this->hasMany(CaseRecord::class);
    }

    /** @param  Builder<Population>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<Population>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('label');
    }
}
