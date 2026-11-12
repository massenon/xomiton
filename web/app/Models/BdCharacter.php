<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Personnage du casting des conversations en BD (P20-03, MODELES.md §1 ; `bd_characters`) : clé stable lue par les bulles,
 * nom affiché et annoncé aux lecteurs d'écran, pronom, rôle, description pour les consignes d'image.
 */
class BdCharacter extends Model
{
    protected $table = 'bd_characters';

    /** @var list<string> */
    protected $fillable = ['key', 'name', 'pronoun', 'role', 'description', 'is_secondary', 'is_active', 'position'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_secondary' => 'boolean', 'is_active' => 'boolean', 'position' => 'integer'];
    }

    /** @param  Builder<BdCharacter>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('key');
    }

    /** @return array<string, string> clé => nom, pour le rendu des bulles */
    public static function names(): array
    {
        /** @var array<string, string> $names */
        $names = self::query()->pluck('name', 'key')->map(static fn (mixed $name): string => (string) $name)->all();

        return $names;
    }
}
