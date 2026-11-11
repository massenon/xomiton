<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * Point d'accès statique aux paramètres de la plateforme, à la place d'un helper global `setting()`
 * (pas de fichier app/Support/helpers.php : composer.json ne doit pas être modifié).
 * Usage : `Settings::get('site_name', 'Xomiton')`, `Settings::bool('maintenance')`, `Settings::set('maintenance', true)`.
 */
final class Settings
{
    private function __construct() {}

    public static function get(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return filter_var(Setting::get($key, $default), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = Setting::get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = Setting::get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    public static function array(string $key, array $default = []): array
    {
        $value = Setting::get($key, $default);

        return is_array($value) ? $value : $default;
    }

    public static function set(string $key, mixed $value, ?int $updatedBy = null): Setting
    {
        return Setting::set($key, $value, $updatedBy);
    }

    public static function has(string $key): bool
    {
        return Setting::has($key);
    }

    /**
     * @return array<string, mixed>
     */
    public static function group(string $group): array
    {
        return Setting::group($group);
    }

    /**
     * @return array<string, mixed>
     */
    public static function public(): array
    {
        return Setting::publicValues();
    }

    public static function flush(): void
    {
        Setting::flushCache();
    }
}
