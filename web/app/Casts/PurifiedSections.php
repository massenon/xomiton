<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Sections d'accordéon `[{title, body}]` en JSON (`thematiques.sections`) : `body` nettoyé à l'écriture comme
 * PurifiedHtml ; `title` reste du texte brut, échappé à l'affichage. Les entrées qui ne sont pas des tableaux sont écartées.
 *
 * @implements CastsAttributes<list<array<string, mixed>>|null, iterable<mixed>|null>
 */
final class PurifiedSections implements CastsAttributes
{
    public function __construct(private readonly string $profile = 'content') {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<array<string, mixed>>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = is_array($value) ? $value : json_decode((string) $value, true);

        return is_array($decoded) ? array_values($decoded) : null;
    }

    /** @param  array<string, mixed>  $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $sections = [];
        foreach (is_iterable($value) ? $value : [] as $section) {
            if (! is_array($section)) {
                continue;
            }

            if (array_key_exists('body', $section)) {
                $section['body'] = (string) PurifiedHtml::clean($section['body'], $this->profile);
            }

            $sections[] = $section;
        }

        return json_encode($sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
