<?php

declare(strict_types=1);

namespace App\Application\Admin\Referentials;

use App\Application\Shared\Countries;
use App\Enums\DirectoryRole;
use Illuminate\Validation\Rule;

/**
 * Règles de validation des formulaires de référentiels (administration). Le formulaire est commun (`label`, `slug`, …) ;
 * le slug est un code stable (clé des API, des signalements et des imports) : il ne change plus après la création.
 */
final class ReferentialRules
{
    /** Code stable : minuscules, chiffres, traits d'union. */
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** Code d'un besoin : le trait de soulignement est admis (`soutien_juridique`). */
    public const NEED_SLUG_PATTERN = '/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/';

    /** Icône Bootstrap Icons (`bi-hand-index-thumb`). */
    public const ICON_PATTERN = '/^bi-[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** Longueur maximale du code d'un type de violence (`alerts.category` : 20 caractères). */
    public const VIOLENCE_SLUG_MAX = 20;

    private function __construct() {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function zone(?int $id, string $countryCode): array
    {
        return [
            'country_code' => ['required', 'string', 'size:2', Rule::in(Countries::CODES)],
            'label' => ['required', 'string', 'min:2', 'max:60', Rule::unique('zones', 'name')->where('country_code', $countryCode)->ignore($id)],
            'slug' => ['required', 'string', 'max:60', 'regex:'.self::SLUG_PATTERN, Rule::unique('zones', 'slug')->where('country_code', $countryCode)->ignore($id)],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function commune(?int $id, int $zoneId): array
    {
        return [
            'label' => ['required', 'string', 'min:2', 'max:80'],
            'slug' => ['required', 'string', 'max:80', 'regex:'.self::SLUG_PATTERN, Rule::unique('communes', 'slug')->where('zone_id', $zoneId)->ignore($id)],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function violenceType(?int $id): array
    {
        return [
            'label' => ['required', 'string', 'min:2', 'max:80'],
            'slug' => ['required', 'string', 'max:'.self::VIOLENCE_SLUG_MAX, 'regex:'.self::SLUG_PATTERN, Rule::unique('violence_types', 'slug')->ignore($id)],
            'icon' => ['required', 'string', 'max:40', 'regex:'.self::ICON_PATTERN],
            'description' => ['required', 'string', 'min:2', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function need(?int $id): array
    {
        return [
            'label' => ['required', 'string', 'min:2', 'max:80'],
            'slug' => ['required', 'string', 'max:40', 'regex:'.self::NEED_SLUG_PATTERN, Rule::unique('needs', 'slug')->ignore($id)],
            'routed_role' => ['nullable', 'string', Rule::in(array_map(static fn (DirectoryRole $role): string => $role->value, DirectoryRole::cases()))],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function population(?int $id): array
    {
        return [
            'label' => ['required', 'string', 'min:2', 'max:80'],
            'slug' => ['required', 'string', 'max:40', 'regex:'.self::SLUG_PATTERN, Rule::unique('populations', 'slug')->ignore($id)],
            'is_active' => ['boolean'],
        ];
    }
}
