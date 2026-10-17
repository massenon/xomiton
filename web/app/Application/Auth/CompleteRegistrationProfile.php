<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Domain\Auth\ProfileCompletion;
use App\Enums\MemberType;
use App\Enums\UserStatus;
use App\Models\Profile;
use App\Models\User;
use App\Rules\PhoneBj;
use App\Support\Audit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Phases 2 et 3 de l'inscription, facultatives (« Passer ») : pronoms, département → commune, type de membre,
 * contact de confiance (chiffré), mode discret. Choisir « pair éducateur·rice » place le compte `en_attente`
 * et lui donne le rôle `pair` (RG-03) : la session reste ouverte, l'accès à la collecte attend la validation.
 */
final class CompleteRegistrationProfile
{
    public const PEER_ROLE = 'pair';

    public function __invoke(User $user, ProfileData $data): void
    {
        DB::transaction(function () use ($user, $data): void {
            $profile = $user->profile ?? Profile::query()->create(['user_id' => $user->getKey()]);

            $this->applyProfile($profile, $data);
            $this->applyLocation($user, $data);

            if ($data->memberType === MemberType::Peer) {
                $this->requestPeerStatus($user, $profile);
            }

            $profile->completion = $this->completion($user, $profile);
            $profile->save();
            $user->save();
        });

        $user->load('profile');
    }

    private function applyProfile(Profile $profile, ProfileData $data): void
    {
        if ($data->pronouns !== null) {
            $profile->pronouns = mb_substr(trim($data->pronouns), 0, 30) ?: null;
        }

        if ($data->trustedContactPhone !== null) {
            $profile->trusted_contact_phone = PhoneBj::normalize($data->trustedContactPhone);
        }

        if ($data->trustedContactName !== null) {
            $profile->trusted_contact_name = mb_substr(trim($data->trustedContactName), 0, 80) ?: null;
        }

        if ($data->discreetMode !== null) {
            $profile->discreet_mode = $data->discreetMode;
        }

        if ($data->memberType !== null) {
            $profile->member_type = $data->memberType;
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

    private function requestPeerStatus(User $user, Profile $profile): void
    {
        $profile->member_type = MemberType::Peer;

        if ($user->status !== UserStatus::Suspended) {
            $user->status = UserStatus::Pending;
        }

        $user->syncRoles([Role::findOrCreate(self::PEER_ROLE, RolesAndPermissionsSeeder::GUARD)]);

        Audit::log('auth.peer_requested', $user, null, $user);
    }

    private function completion(User $user, Profile $profile): int
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
