<?php

declare(strict_types=1);

namespace App\Application\Account\Profile;

use App\Domain\Account\ProgressiveProfile;
use App\Enums\MemberType;
use App\Models\Profile;
use App\Models\User;

/**
 * État du bandeau « Complète ton profil, c'est facultatif » (P8-08) pour un compte : groupe à proposer, groupes
 * traités, consentement `sensitive_data` en cours. Lu par la vue « moi » (UserResource, `GET me/profile`) et la page
 * `/profil` ; jamais pour un autre compte.
 */
final class ProgressiveProfileState
{
    public function __construct(private readonly SensitiveDataConsent $consent) {}

    /**
     * @return array{groups: list<array{key: string, answered: bool}>, next: string|null, done: bool, answered: int, total: int, sensitive_consent: bool}
     */
    public function for(User $user): array
    {
        $profile = $user->profile;

        return ProgressiveProfile::state(self::filled($user, $profile), $profile?->progressive_answered)
            + ['sensitive_consent' => $this->consent->granted($user)];
    }

    /**
     * @return array<string, bool>
     */
    private static function filled(User $user, ?Profile $profile): array
    {
        // Un compte qui a déjà un autre rôle que `membre` (pair, personnel) n'a pas à choisir son type
        // (CompleteRegistrationProfile::STAFF_ROLES l'ignore d'ailleurs côté serveur).
        $hasOtherRole = $user->getRoleNames()->contains(static fn (string $role): bool => $role !== 'membre');

        if ($profile === null) {
            return ['member_type' => $hasOtherRole];
        }

        return [
            'pronouns' => $profile->pronouns !== null && $profile->pronouns !== '',
            // `member_type` vaut « membre » par défaut : seul un choix explicite (ou « pair ») traite le groupe.
            'member_type' => $hasOtherRole || ($profile->member_type !== null && $profile->member_type !== MemberType::Member),
            'ally_status' => $profile->ally_status !== null,
            'gender_identity' => $profile->gender_identity !== null,
            'orientation' => $profile->orientation !== null,
            'age' => $profile->age_range !== null,
            'location' => $user->zone_id !== null,
            'interests' => ! empty($profile->interests),
        ];
    }
}
