<?php

declare(strict_types=1);

namespace App\Application\Shared;

use App\Models\User;
use App\Models\UserSetting;
use InvalidArgumentException;

/**
 * Préférences de notification par catégorie (P3-02, NOTIFICATIONS_INSTRUCTION.md §4.3, DATABASE_DICTIONARY.md
 * `user_settings.notification_prefs`) : trois canaux par catégorie (`in_app` = cloche, `email` = template en base,
 * `push` = Web Push / FCM). Les valeurs par défaut et les catégories verrouillées (sécurité du compte, SOS : jamais
 * désactivables) viennent de `config('xomiton.notifications.categories')`. Les préférences stockées sont toujours
 * relues à travers `normalize()` : catégorie inconnue ignorée, catégorie verrouillée forcée à vrai, canal absent → défaut.
 */
final class NotificationPreferences
{
    public const CHANNEL_IN_APP = 'in_app';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_PUSH = 'push';

    /** @var list<string> */
    public const CHANNELS = [self::CHANNEL_IN_APP, self::CHANNEL_EMAIL, self::CHANNEL_PUSH];

    /**
     * Catégories connues, dans l'ordre de la configuration.
     *
     * @return list<string>
     */
    public static function categories(): array
    {
        return array_keys(self::config());
    }

    /**
     * Catégories que la personne ne peut pas désactiver.
     *
     * @return list<string>
     */
    public static function lockedCategories(): array
    {
        return array_keys(array_filter(self::config(), static fn (array $category): bool => (bool) ($category['locked'] ?? false)));
    }

    public static function isLocked(string $category): bool
    {
        return in_array($category, self::lockedCategories(), true);
    }

    public static function isKnown(string $category): bool
    {
        return array_key_exists($category, self::config());
    }

    /**
     * Valeurs par défaut (sans le drapeau `locked`).
     *
     * @return array<string, array{in_app: bool, email: bool, push: bool}>
     */
    public static function defaults(): array
    {
        $defaults = [];

        foreach (self::config() as $category => $values) {
            $defaults[$category] = self::channelsOf($values, null);
        }

        return $defaults;
    }

    /**
     * Préférences complètes et cohérentes à partir d'un tableau stocké (ou nul) : chaque catégorie connue, chaque
     * canal, les catégories verrouillées à vrai.
     *
     * @param  array<array-key, mixed>|null  $stored
     * @return array<string, array{in_app: bool, email: bool, push: bool}>
     */
    public static function normalize(?array $stored): array
    {
        $locked = self::lockedCategories();
        $normalized = [];

        foreach (self::config() as $category => $defaults) {
            $values = is_array($stored[$category] ?? null) ? $stored[$category] : [];

            $normalized[$category] = in_array($category, $locked, true)
                ? [self::CHANNEL_IN_APP => true, self::CHANNEL_EMAIL => true, self::CHANNEL_PUSH => true]
                : self::channelsOf($defaults, $values);
        }

        return $normalized;
    }

    /**
     * Un canal est-il actif pour une catégorie ? Catégorie verrouillée → toujours ; catégorie inconnue → oui
     * (une notification sans préférence n'est jamais perdue).
     *
     * @param  array<array-key, mixed>|null  $stored
     */
    public static function allows(?array $stored, string $category, string $channel): bool
    {
        if (self::isLocked($category) || ! self::isKnown($category)) {
            return true;
        }

        return self::normalize($stored)[$category][$channel] ?? true;
    }

    /**
     * Préférences effectives de la personne (réglages absents → défauts).
     *
     * @return array<string, array{in_app: bool, email: bool, push: bool}>
     */
    public function get(User $user): array
    {
        return self::normalize($user->settings?->notification_prefs);
    }

    public function wants(User $user, string $category, string $channel): bool
    {
        return self::allows($user->settings?->notification_prefs, $category, $channel);
    }

    /**
     * Enregistre des préférences partielles (`catégorie => [canal => bool]`) fusionnées avec les préférences
     * courantes ; une catégorie ou un canal inconnu est refusé ; sécurité et SOS restent actifs quoi qu'il arrive.
     *
     * @param  array<string, array<string, mixed>>  $prefs
     * @return array<string, array{in_app: bool, email: bool, push: bool}>
     *
     * @throws InvalidArgumentException
     */
    public function set(User $user, array $prefs): array
    {
        $current = $this->get($user);

        foreach ($prefs as $category => $channels) {
            if (! is_string($category) || ! self::isKnown($category)) {
                throw new InvalidArgumentException(sprintf('Unknown notification category [%s].', is_scalar($category) ? (string) $category : gettype($category)));
            }

            if (! is_array($channels)) {
                throw new InvalidArgumentException(sprintf('Preferences of category [%s] must be an array of channels.', $category));
            }

            foreach ($channels as $channel => $enabled) {
                if (! in_array($channel, self::CHANNELS, true)) {
                    throw new InvalidArgumentException(sprintf('Unknown notification channel [%s].', is_scalar($channel) ? (string) $channel : gettype($channel)));
                }

                $current[$category][$channel] = filter_var($enabled, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $enabled;
            }
        }

        $normalized = self::normalize($current);

        $settings = $user->settings ?? UserSetting::query()->firstOrNew(['user_id' => $user->getKey()]);
        $settings->notification_prefs = $normalized;
        $settings->save();
        $user->setRelation('settings', $settings);

        return $normalized;
    }

    /**
     * @return array<string, array{in_app: bool, email: bool, push: bool, locked?: bool}>
     */
    private static function config(): array
    {
        /** @var array<string, array{in_app?: bool, email?: bool, push?: bool, locked?: bool}> $categories */
        $categories = (array) config('xomiton.notifications.categories', []);

        return $categories;
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>|null  $values
     * @return array{in_app: bool, email: bool, push: bool}
     */
    private static function channelsOf(array $defaults, ?array $values): array
    {
        $result = [];

        foreach (self::CHANNELS as $channel) {
            $default = (bool) ($defaults[$channel] ?? true);
            $value = $values[$channel] ?? null;

            $result[$channel] = $value === null ? $default : (filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default);
        }

        return $result;
    }
}
