<?php

declare(strict_types=1);

namespace App\Application\Account;

use App\Application\Shared\NotificationPreferences;
use App\Models\User;
use App\Models\UserSetting;

/**
 * Ligne `user_settings` d'un compte, créée avec les valeurs par défaut du dictionnaire si elle manque (comptes importés
 * ou créés avant P3-02). Mêmes valeurs que App\Application\Auth\UpdateUserSettings.
 */
final class UserSettings
{
    public static function ensure(User $user): UserSetting
    {
        $settings = $user->settings ?? UserSetting::query()->firstOrNew(['user_id' => $user->getKey()]);

        if (! $settings->exists) {
            $settings->fill([
                'theme' => 'auto',
                'language' => (string) config('app.locale', 'fr'),
                'notification_prefs' => NotificationPreferences::defaults(),
                'notification_digest' => UserSetting::DIGEST_NEVER,
            ]);
            $settings->save();
            $settings->refresh();
        }

        $user->setRelation('settings', $settings);

        return $settings;
    }

    /**
     * Champs d'écriture du prototype mobile relus sous le même nom (GET me/settings, P6-06) : `locale` (= `language`),
     * `sounds` (= `sounds_enabled`), `notifications` (interrupteur global : vrai si au moins une catégorie
     * désactivable garde le push ; sécurité et SOS restent toujours actives). `theme` reste la valeur enregistrée
     * (`auto` = `system` du prototype, docs/API.md §3).
     *
     * @return array{locale: string, sounds: bool, notifications: bool}
     */
    public static function writeAliases(UserSetting $settings): array
    {
        $prefs = $settings->notificationPrefs();
        $push = false;

        foreach (NotificationPreferences::categories() as $category) {
            if (! NotificationPreferences::isLocked($category) && (bool) ($prefs[$category][NotificationPreferences::CHANNEL_PUSH] ?? false)) {
                $push = true;
                break;
            }
        }

        return [
            'locale' => (string) $settings->language,
            'sounds' => (bool) ($settings->sounds_enabled ?? true),
            'notifications' => $push,
        ];
    }
}
