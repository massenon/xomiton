<?php

declare(strict_types=1);

namespace App\Application\Account\Settings;

use App\Application\Auth\CompleteRegistrationProfile;
use App\Models\Profile;
use App\Models\User;
use App\Rules\PhoneBj;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Section « Profil » de /parametres (P8-09, prototype settings.php `action=profile`) : nom complet (jamais public, RG-02),
 * pseudonyme (unique sans casse), téléphone béninois normalisé (jamais public), département et commune du compte,
 * présentation (`profiles.bio`). Le nom est facultatif (vide = effacé). Un nouveau pseudonyme réserve l'ancien et ouvre
 * le délai avant le suivant (PseudonymChanges, Q81). Zone, commune et présentation comptent dans `profiles.completion` :
 * le taux est recalculé comme par `PATCH me/profile` (CompleteRegistrationProfile::completion). Les champs du profil
 * progressif (pronoms, centres d'intérêt, données sensibles) restent sur `PATCH me/profile` (P6-06, P8-08). Journal
 * `me.settings_updated` (section profil) : noms des champs, jamais les valeurs.
 */
final class UpdateAccountIdentity
{
    public function __construct(private readonly PseudonymChanges $pseudonyms) {}

    /**
     * @param  array{name?: string|null, pseudo?: string, phone?: string|null, bio?: string|null, zone_id?: int|null, commune_id?: int|null}  $data
     */
    public function __invoke(User $user, array $data): User
    {
        if ($data === []) {
            return $user;
        }

        $previousPseudo = (string) $user->pseudo;

        DB::transaction(function () use ($user, $data): void {
            if (array_key_exists('name', $data)) {
                $name = trim((string) $data['name']);
                $user->name = $name === '' ? null : $name;
            }

            if (array_key_exists('pseudo', $data)) {
                $user->pseudo = trim((string) $data['pseudo']);
            }

            if (array_key_exists('phone', $data)) {
                $phone = trim((string) $data['phone']);
                $user->phone = $phone === '' ? null : PhoneBj::normalize($phone);
            }

            if (array_key_exists('zone_id', $data)) {
                $user->zone_id = $data['zone_id'];
                // Changer de département sans commune efface l'ancienne commune (liste en cascade).
                $user->commune_id = $data['commune_id'] ?? null;
            } elseif (array_key_exists('commune_id', $data)) {
                $user->commune_id = $data['commune_id'];
            }

            $user->save();

            $profile = $user->profile ?? Profile::query()->create(['user_id' => $user->getKey()]);

            if (array_key_exists('bio', $data)) {
                $bio = trim((string) $data['bio']);
                $profile->bio = $bio === '' ? null : $bio;
            }

            if (array_key_exists('zone_id', $data) && $data['zone_id'] !== null) {
                // Même groupe que le bandeau du profil progressif (CompleteRegistrationProfile).
                $profile->markAnswered('location');
            }

            $profile->completion = CompleteRegistrationProfile::completion($user, $profile);
            $profile->save();
            $user->setRelation('profile', $profile);
        });

        if (array_key_exists('pseudo', $data) && PseudonymChanges::differs($previousPseudo, (string) $user->pseudo)) {
            $this->pseudonyms->record($user, $previousPseudo);
        }

        Audit::log('me.settings_updated', $user, 'section=profil ; champs='.implode(',', array_keys($data)), $user);

        return $user;
    }
}
