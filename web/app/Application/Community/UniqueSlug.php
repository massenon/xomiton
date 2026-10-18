<?php

declare(strict_types=1);

namespace App\Application\Community;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Slug unique d'un groupe ou d'une page (colonne `slug unique`, suppressions logiques comprises) : slug du nom, suffixé
 * d'un court aléa en cas de collision. Les adresses publiques restent l'ULID (ADR-27) ; le slug sert à l'import.
 */
final class UniqueSlug
{
    /**
     * @param  class-string<Model>  $model
     */
    public static function for(string $model, string $name, int $max = 140): string
    {
        $base = Str::limit(Str::slug($name), $max - 7, '') ?: 'x';
        $slug = $base;

        while ($model::query()->withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }
}
