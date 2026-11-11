<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DirectoryRole;
use Database\Factories\NeedFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Besoin exprimé dans un signalement ; `routed_role` désigne le rôle d'annuaire ciblé
 * par le routage (RG-04), null = point focal.
 */
class Need extends Model
{
    /** @use HasFactory<NeedFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'label',
        'routed_role',
        'position',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'routed_role' => DirectoryRole::class,
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @param  Builder<Need>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<Need>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('label');
    }

    /**
     * Besoins routés vers un rôle donné (null : ceux qui relèvent du point focal).
     *
     * @param  Builder<Need>  $query
     */
    public function scopeRoutedTo(Builder $query, ?DirectoryRole $role): void
    {
        $role === null
            ? $query->whereNull('routed_role')
            : $query->where('routed_role', $role->value);
    }

    /** Rôle effectivement ciblé : le rôle déclaré, sinon le point focal. */
    public function targetRole(): DirectoryRole
    {
        return $this->routed_role ?? DirectoryRole::PointFocal;
    }
}
