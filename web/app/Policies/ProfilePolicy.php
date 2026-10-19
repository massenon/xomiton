<?php

declare(strict_types=1);

namespace App\Policies;

use App\Application\Account\Profile\MemberProfiles;
use App\Models\Profile;
use App\Models\User;

/**
 * Profils (P8-07, P8-08) : voir un profil suit App\Domain\Account\ProfileAudience (MemberProfiles::canView : soi-même ;
 * sinon `social.access`, visibilité, blocages, compte joignable) ; modifier son profil, ses données sensibles, son
 * avatar et sa couverture, et lire son journal d'activité : la personne elle-même seulement (l'administration passe
 * par UserPolicy).
 */
final class ProfilePolicy
{
    public function __construct(private readonly MemberProfiles $profiles) {}

    public function view(User $viewer, Profile $profile): bool
    {
        $owner = $profile->user;

        return $owner instanceof User && $this->profiles->canView($viewer, $owner);
    }

    public function update(User $viewer, Profile $profile): bool
    {
        return (int) $viewer->getKey() === (int) $profile->user_id;
    }

    public function viewActivity(User $viewer, Profile $profile): bool
    {
        return $this->update($viewer, $profile);
    }
}
