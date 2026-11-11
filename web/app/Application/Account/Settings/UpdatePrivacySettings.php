<?php

declare(strict_types=1);

namespace App\Application\Account\Settings;

use App\Application\Account\Profile\SensitiveDataConsent;
use App\Application\Account\UserSettings;
use App\Enums\ProfileVisibility;
use App\Models\Profile;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Confidentialité du profil (P8-09, prototype settings.php « Confidentialité ») : qui voit mon profil
 * (`profiles.visibility`), afficher ma zone (`show_zone`), apparaître dans « Qui suivre » (`searchable`) et consentement
 * au partage de la position lors d'un SOS (`user_settings.sos_share_gps`), consentement aux données sensibles
 * (SensitiveDataConsent, aussi simple à retirer qu'à donner : une nouvelle ligne `consents` seulement si la réponse
 * change ; un retrait efface identité de genre et orientation). Chaque champ absent est laissé tel quel ; journal
 * `me.settings_updated` (section confidentialite) avec la liste des champs, jamais leurs valeurs.
 */
final class UpdatePrivacySettings
{
    public function __construct(private readonly SensitiveDataConsent $consent) {}

    /**
     * @param  array{visibility?: ProfileVisibility, show_zone?: bool, searchable?: bool, sos_share_gps?: bool, sensitive_consent?: bool}  $data
     */
    public function __invoke(User $user, array $data): User
    {
        if ($data === []) {
            return $user;
        }

        DB::transaction(function () use ($user, $data): void {
            $profile = $user->profile ?? Profile::query()->create(['user_id' => $user->getKey()]);
            $settings = $user->settings ?? UserSettings::ensure($user);

            foreach (['visibility', 'show_zone', 'searchable'] as $field) {
                if (array_key_exists($field, $data)) {
                    $profile->{$field} = $data[$field];
                }
            }

            if (array_key_exists('sensitive_consent', $data) && $data['sensitive_consent'] !== $this->consent->granted($user)) {
                $this->consent->record($user, $data['sensitive_consent'], $profile);
            }

            if (array_key_exists('sos_share_gps', $data)) {
                $settings->sos_share_gps = $data['sos_share_gps'];
                $settings->save();
            }

            $profile->save();
            $user->setRelation('profile', $profile);
            $user->setRelation('settings', $settings);
        });

        Audit::log('me.settings_updated', $user, 'section=confidentialite ; champs='.implode(',', array_keys($data)), $user);

        return $user;
    }
}
