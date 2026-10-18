<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Shared\NotificationPreferences;
use App\Models\User;
use App\Models\UserSetting;
use App\Support\Audit;

/**
 * Mise à jour des réglages de la personne connectée (PATCH me/settings, P3-02) : réglages simples (thème, langue,
 * résumé, discrétion, GPS, titre neutre) et préférences de notification par catégorie via NotificationPreferences
 * (sécurité du compte et SOS jamais désactivables). Crée la ligne `user_settings` si elle manque. Journal `me.settings_updated`
 * avec la liste des champs touchés, jamais leurs valeurs.
 */
final class UpdateUserSettings
{
    /** @var list<string> */
    public const SIMPLE_FIELDS = ['theme', 'language', 'notification_digest', 'mask_sensitive_notifications', 'sos_share_gps', 'neutral_tab_title'];

    public function __construct(private readonly NotificationPreferences $preferences) {}

    /**
     * @param  array<string, mixed>  $data  champs validés présents dans la requête
     */
    public function __invoke(User $user, array $data): UserSetting
    {
        $settings = $user->settings ?? UserSetting::query()->firstOrNew(['user_id' => $user->getKey()]);

        if (! $settings->exists) {
            $settings->fill([
                'theme' => 'auto',
                'language' => (string) config('app.locale', 'fr'),
                'notification_prefs' => NotificationPreferences::defaults(),
                'notification_digest' => UserSetting::DIGEST_NEVER,
            ]);
        }

        $touched = [];

        foreach (self::SIMPLE_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $settings->{$field} = $data[$field];
                $touched[] = $field;
            }
        }

        $settings->save();
        $user->setRelation('settings', $settings);

        if (isset($data['notification_prefs']) && is_array($data['notification_prefs'])) {
            /** @var array<string, array<string, mixed>> $prefs */
            $prefs = $data['notification_prefs'];
            $this->preferences->set($user, $prefs);
            $touched[] = 'notification_prefs';
        }

        if ($touched !== []) {
            Audit::log('me.settings_updated', $user, 'champs='.implode(',', $touched), $user);
        }

        return $user->settings ?? $settings;
    }
}
