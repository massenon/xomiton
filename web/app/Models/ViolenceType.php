<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ViolenceTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Type de violence (physique, psychologique, sexuelle, économique, numérique).
 */
class ViolenceType extends Model
{
    /** @use HasFactory<ViolenceTypeFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'label',
        'icon',
        'description',
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

    /** @param  Builder<ViolenceType>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<ViolenceType>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('label');
    }
}
