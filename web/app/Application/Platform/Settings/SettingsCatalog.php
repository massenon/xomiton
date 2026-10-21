<?php

declare(strict_types=1);

namespace App\Application\Platform\Settings;

use App\Support\Settings;
use InvalidArgumentException;

/**
 * Catalogue des paramètres typés de la plateforme (P13-04) : lit `config('xomiton.platform_settings')`. Une clé hors
 * catalogue n'est jamais écrite par les écrans d'administration ; une clé absente de la base vaut son défaut.
 */
final class SettingsCatalog
{
    /**
     * @return array<string, SettingDefinition>
     */
    public static function all(): array
    {
        $definitions = [];

        foreach ((array) config('xomiton.platform_settings', []) as $key => $entry) {
            if (! is_string($key) || ! is_array($entry)) {
                continue;
            }

            $definitions[$key] = new SettingDefinition(
                key: $key,
                group: (string) ($entry['group'] ?? 'general'),
                type: (string) ($entry['type'] ?? 'string'),
                default: $entry['default'] ?? null,
                public: (bool) ($entry['public'] ?? false),
                rules: array_values(array_map('strval', (array) ($entry['rules'] ?? []))),
                itemRules: array_values(array_map('strval', (array) ($entry['item_rules'] ?? []))),
            );
        }

        return $definitions;
    }

    public static function has(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    public static function definition(string $key): SettingDefinition
    {
        return self::all()[$key] ?? throw new InvalidArgumentException('Paramètre inconnu : '.$key);
    }

    /**
     * @return list<string>
     */
    public static function keys(string ...$groups): array
    {
        $keys = [];

        foreach (self::all() as $key => $definition) {
            if ($groups === [] || in_array($definition->group, $groups, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /** Valeur courante (base, sinon défaut du catalogue), typée ; jamais le contenu d'un secret. */
    public static function value(string $key): mixed
    {
        $definition = self::definition($key);

        if ($definition->isSecret()) {
            return null;
        }

        $value = Settings::get($key, $definition->default);

        return match ($definition->type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $definition->default,
            'int' => is_numeric($value) ? (int) $value : (int) $definition->default,
            'json' => is_array($value) ? $value : (array) $definition->default,
            default => is_scalar($value) ? (string) $value : (string) $definition->default,
        };
    }

    /** Un secret est-il renseigné ? (l'écran affiche « défini », jamais la valeur) */
    public static function secretIsSet(string $key): bool
    {
        return Settings::has($key) && Settings::get($key) !== null && Settings::get($key) !== '';
    }
}
