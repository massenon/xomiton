<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Identifiant public ULID (ADR-27) pour les contenus exposés par URL : généré à la création
 * si la colonne `ulid` est vide, jamais régénéré ensuite.
 */
trait HasUlid
{
    public static function bootHasUlid(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->getAttribute('ulid'))) {
                $model->setAttribute('ulid', (string) Str::ulid());
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeWhereUlid(Builder $query, string $ulid): void
    {
        $query->where($this->getTable().'.ulid', $ulid);
    }
}
