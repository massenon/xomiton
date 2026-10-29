<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Domain\Auth\ProfileCompletion;
use App\Enums\MemberType;
use App\Models\Profile;
use App\Models\User;
use App\Rules\PhoneBj;
use Illuminate\Support\Facades\DB;

/**
 * Phases 2 et 3 de l'inscription, facultatives (« Passer ») : pronoms, département → commune, type de membre,
 * contact de confiance (chiffré), mode discret ; profil progressif de l'API (P6-06) : tranche d'âge, centres
 * d'intérêt, statut allié·e / personne ressource (chiffré, Q10 c, aucun droit associé). **Aucun rôle ne s'obtient par ce
 * chemin** (décisions client du 2026-10-29) : `member_type = pair` est ignoré, le compte reste membre et actif ; le rôle
 * pair vient d'une invitation de l'équipe ou d'une attribution de l'administration. Un compte du personnel (STAFF_ROLES)
 * ne change jamais de type par ce chemin non plus.
 */
final class CompleteRegistrationProfile
{
    public const PEER_ROLE = 'pair';

    /** Rôles du personnel : leur type de membre ne se choisit pas dans le profil (ROLE_ET_PERMISSIONS.md §1). */
    public const STAFF_ROLES = ['moderateur', 'admin', 'superadmin'];

    public static function isStaff(User $user): bool
    {
        return $user->hasAnyRole(self::STAFF_ROLES);
    }

    public function __invoke(User $user, ProfileData $data): void
    {
        DB::transaction(function () use ($user, $data): void {
            $profile = $user->profile ?? Profile::query()->create(['user_id' => $user->getKey()]);

            $this->applyProfile($profile, $data, self::isStaff($user));
            $this->applyLocation($user, $data);

            if ($data->zoneId !== null) {
                $profile->markAnswered('location');
            }

            $profile->completion = self::completion($user, $profile);
            $profile->save();
            $user->save();
        });

        $user->load('profile');
    }

    private function applyProfile(Profile $profile, ProfileData $data, bool $staff): void
    {
        if ($data->clearPronouns) {
            $profile->pronouns = null;
            $profile->markAnswered('pronouns');
        } elseif ($data->pronouns !== null) {
            $profile->pronouns = mb_substr(trim($data->pronouns), 0, 30) ?: null;
            $profile->markAnswered('pronouns');
        }

        if ($data->trustedContactPhone !== null) {
            $profile->trusted_contact_phone = PhoneBj::format($data->trustedContactPhone);
        }

        if ($data->trustedContactName !== null) {
            $profile->trusted_contact_name = mb_substr(trim($data->trustedContactName), 0, 80) ?: null;
        }

        if ($data->discreetMode !== null) {
            $profile->discreet_mode = $data->discreetMode;
        }

        if ($data->memberType !== null) {
            // Jamais « pair » (rôle attribué par l'équipe) ni le type d'un compte du personnel ; l'état d'un compte pair
            // existant ne se défait pas par ce chemin.
            if (! $staff && $data->memberType !== MemberType::Peer && $profile->member_type !== MemberType::Peer) {
                $profile->member_type = $data->memberType;
            }

            $profile->markAnswered('member_type');
        }

        // Profil progressif (PATCH me/profile, P6-06) ; chaque groupe renseigné n'est plus reproposé (P8-08).
        if ($data->clearAgeRange) {
            $profile->age_range = null;
            $profile->markAnswered('age');
        } elseif ($data->ageRange !== null) {
            $profile->age_range = $data->ageRange;
            $profile->markAnswered('age');
        }

        if ($data->interests !== null) {
            $interests = array_values(array_unique($data->interests));
            $profile->interests = $interests === [] ? null : $interests;
            $profile->markAnswered('interests');
        }

        if ($data->clearAllyStatus) {
            $profile->ally_status = null;
            $profile->markAnswered('ally_status');
        } elseif ($data->allyStatus !== null) {
            $profile->ally_status = $data->allyStatus->value;
            $profile->markAnswered('ally_status');
        }
    }

    private function applyLocation(User $user, ProfileData $data): void
    {
        if ($data->zoneId !== null) {
            $user->zone_id = $data->zoneId;
        }

        if ($data->communeId !== null) {
            $user->commune_id = $data->communeId;
        }
    }

    /**
     * Taux de complétion (`profiles.completion`) : groupes de ProfileCompletion ; les champs sensibles (identité de genre,
     * orientation, statut allié·e) n'y comptent jamais (aucune incitation à les donner, P8-08). Relu aussi après un
     * changement d'avatar ou de couverture (App\Application\Account\Profile\UpdateProfileImage).
     */
    public static function completion(User $user, Profile $profile): int
    {
        return ProfileCompletion::fromFlags([
            'pronouns' => $profile->pronouns !== null,
            'zone' => $user->zone_id !== null,
            'commune' => $user->commune_id !== null,
            'bio' => $profile->bio !== null && $profile->bio !== '',
            'avatar' => $profile->avatar_path !== null,
            'age_range' => $profile->age_range !== null,
            'trusted_contact' => $profile->trusted_contact_phone !== null,
        ]);
    }
}
