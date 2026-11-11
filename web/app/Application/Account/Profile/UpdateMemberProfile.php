<?php

declare(strict_types=1);

namespace App\Application\Account\Profile;

use App\Application\Auth\CompleteRegistrationProfile;
use App\Application\Auth\ProfileData;
use App\Domain\Account\SensitiveProfile;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * PATCH me/profile (P6-06, P8-08) : champs ordinaires par CompleteRegistrationProfile (phases 2-3, tranche d'âge,
 * centres d'intérêt, statut allié·e, complétion recalculée), puis, dans la même transaction, la partie sensible :
 *
 *  1. réponse au consentement `sensitive_data` (nouvelle ligne `consents` ; un retrait efface genre et orientation) ;
 *  2. identité de genre et orientation (chiffrées, `encrypted_columns.profiles`) — UpdateProfileRequest a déjà refusé
 *     (422) toute valeur sans consentement en cours ou accordé dans la même requête ; `prefer_not` efface ;
 *  3. `shared_fields` : seuls champs sensibles que les autres comptes pourront lire (choix explicite) ;
 *  4. groupes du profil progressif traités sans valeur (« Je préfère ne pas le dire ») ;
 *  5. `show_zone` : interrupteur « Afficher ma zone sur mon profil » du groupe Ville (département seulement pour autrui).
 */
final class UpdateMemberProfile
{
    public function __construct(
        private readonly CompleteRegistrationProfile $completeProfile,
        private readonly SensitiveDataConsent $consent,
    ) {}

    public function __invoke(User $user, ProfileData $data, SensitiveProfileData $sensitive): void
    {
        DB::transaction(function () use ($user, $data, $sensitive): void {
            ($this->completeProfile)($user, $data);

            if ($sensitive->isEmpty()) {
                return;
            }

            $profile = $user->profile ?? Profile::query()->create(['user_id' => $user->getKey()]);

            if ($sensitive->consent !== null) {
                $this->consent->record($user, $sensitive->consent, $profile);
            }

            $this->applySensitive($profile, $sensitive);

            if ($sensitive->sharedFields !== null) {
                $shared = SensitiveProfile::normalizeShared($sensitive->sharedFields);
                $profile->shared_fields = $shared === [] ? null : $shared;
            }

            if ($sensitive->answeredGroups !== []) {
                $profile->markAnswered(...$sensitive->answeredGroups);
            }

            if ($sensitive->showZone !== null) {
                $profile->show_zone = $sensitive->showZone;
            }

            $profile->save();
        });

        $user->load('profile');
    }

    private function applySensitive(Profile $profile, SensitiveProfileData $data): void
    {
        if ($data->clearGenderIdentity) {
            $profile->gender_identity = null;
            $profile->markAnswered(SensitiveProfile::GENDER_IDENTITY);
        } elseif ($data->genderIdentity !== null) {
            $profile->gender_identity = $data->genderIdentity;
            $profile->markAnswered(SensitiveProfile::GENDER_IDENTITY);
        }

        if ($data->clearOrientation) {
            $profile->orientation = null;
            $profile->markAnswered(SensitiveProfile::ORIENTATION);
        } elseif ($data->orientation !== null) {
            $profile->orientation = $data->orientation;
            $profile->markAnswered(SensitiveProfile::ORIENTATION);
        }
    }
}
