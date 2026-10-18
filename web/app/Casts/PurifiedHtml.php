<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Mews\Purifier\Facades\Purifier;

/**
 * HTML éditorial nettoyé à l'écriture (ADR-24, DEVELOPMENT_STANDARDS §7) par un profil de config/purifier.php
 * (`content` par défaut : paragraphes, listes, intertitres, liens, classe `lead` seule ; ni style ni script).
 * Lecture telle quelle : la vue peut l'insérer avec `{!! !!}`. Vide après nettoyage → null.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class PurifiedHtml implements CastsAttributes
{
    public function __construct(private readonly string $profile = 'content') {}

    /** @param  array<string, mixed>  $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : (string) $value;
    }

    /** @param  array<string, mixed>  $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return self::clean($value, $this->profile);
    }

    public static function clean(mixed $value, string $profile = 'content'): ?string
    {
        if (! is_scalar($value) && ! $value instanceof \Stringable) {
            return null;
        }

        if (trim((string) $value) === '') {
            return null;
        }

        $clean = trim((string) Purifier::clean((string) $value, $profile));

        return $clean === '' ? null : $clean;
    }
}
