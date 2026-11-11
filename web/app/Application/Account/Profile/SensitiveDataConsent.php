<?php

declare(strict_types=1);

namespace App\Application\Account\Profile;

use App\Domain\Account\SensitiveProfile;
use App\Domain\Auth\ConsentVersion;
use App\Enums\ConsentKind;
use App\Models\Consent;
use App\Models\Profile;
use App\Models\User;

/**
 * Consentement `sensitive_data` (P8-08, FR-AUTH-07) : en cours si la **dernière** réponse enregistrée est un accord
 * (`consents` n'est jamais mis à jour : chaque réponse est une ligne horodatée). Retirer le consentement efface
 * l'identité de genre et l'orientation du profil et les retire de `shared_fields` (lang/fr/profile.php
 * `settings.privacy.sensitive_consent_help`). Lignes sans IP ni user agent (minimisation, comme la newsletter Q66).
 */
final class SensitiveDataConsent
{
    public function granted(User $user): bool
    {
        $last = Consent::query()
            ->where('user_id', $user->getKey())
            ->ofKind(ConsentKind::SensitiveData)
            ->latest('id')
            ->first(['id', 'granted']);

        return $last !== null && (bool) $last->granted;
    }

    /**
     * Enregistre une réponse (nouvelle ligne) ; un retrait efface aussi les champs qui en dépendent (sans enregistrer le
     * profil : l'appelant le fait).
     */
    public function record(User $user, bool $granted, ?Profile $profile = null): void
    {
        Consent::query()->create([
            'user_id' => $user->getKey(),
            'kind' => ConsentKind::SensitiveData,
            'version' => ConsentVersion::current(),
            'granted' => $granted,
        ]);

        if (! $granted && $profile !== null) {
            self::erase($profile);
        }
    }

    /**
     * Efface l'identité de genre et l'orientation et les retire des champs partagés (N'enregistre pas).
     */
    public static function erase(Profile $profile): void
    {
        $profile->gender_identity = null;
        $profile->orientation = null;

        $shared = array_values(array_diff(SensitiveProfile::normalizeShared($profile->shared_fields), SensitiveProfile::CONSENT_REQUIRED));
        $profile->shared_fields = $shared === [] ? null : $shared;
    }
}
