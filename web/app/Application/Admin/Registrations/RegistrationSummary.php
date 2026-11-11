<?php

declare(strict_types=1);

namespace App\Application\Admin\Registrations;

use App\Application\Shared\Countries;
use App\Enums\AllyRole;
use App\Enums\MemberType;
use App\Models\User;

/**
 * Ce que l'écran Inscriptions de l'administration montre d'un compte en attente (inscription en 3 étapes, décision client
 * du 11/11/2026) : prénom et nom, âge, pays et ville, téléphone, type (membre ou allié·e) et, pour une allié·e, son statut
 * (et la précision si « autre »). L'identité de genre et l'orientation n'y figurent JAMAIS : données sensibles chiffrées,
 * inutiles pour valider un compte.
 */
final class RegistrationSummary
{
    private function __construct() {}

    /**
     * @return array{name: string, age: int|null, place: string|null, phone: string|null, is_ally: bool, ally_role: string|null, ally_role_other: string|null}
     */
    public static function of(User $user): array
    {
        $profile = $user->loadMissing('profile')->profile;
        $name = trim(((string) $user->first_name).' '.((string) $user->last_name));
        $country = $profile?->country_code === null ? null : (Countries::name((string) $profile->country_code) ?? (string) $profile->country_code);
        $city = $profile === null || $profile->city === null || trim((string) $profile->city) === '' ? null : trim((string) $profile->city);
        $place = implode(', ', array_filter([$city, $country], static fn (?string $part): bool => $part !== null && $part !== ''));
        $isAlly = $profile?->member_type === MemberType::Ally;
        $role = $isAlly && $profile !== null && $profile->ally_role !== null ? AllyRole::tryFrom((string) $profile->ally_role) : null;
        $other = $role === AllyRole::Other && $profile !== null && $profile->ally_role_other !== null ? trim((string) $profile->ally_role_other) : null;

        return [
            'name' => $name !== '' ? $name : trim((string) $user->name),
            'age' => $profile?->age === null ? null : (int) $profile->age,
            'place' => $place === '' ? null : $place,
            'phone' => $user->phone === null || trim((string) $user->phone) === '' ? null : (string) $user->phone,
            'is_ally' => $isAlly,
            'ally_role' => $role?->label(),
            'ally_role_other' => $other === '' ? null : $other,
        ];
    }
}
