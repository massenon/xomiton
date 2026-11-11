<?php

declare(strict_types=1);

namespace App\Application\Auth\Support;

use App\Application\Shared\Countries;
use App\Domain\Account\SensitiveProfile;
use App\Enums\AllyRole;
use App\Enums\MemberType;

/**
 * Listes de l'inscription en 3 étapes (décision client du 11/11/2026), partagées par le formulaire web, la validation et
 * `GET /api/v1/referentials/registration` : âge minimal, pronoms, « Vous êtes ? », pays, statuts d'allié·e, identités de
 * genre et orientations (les valeurs « autre » = `autre`, « je préfère ne pas le dire » = `prefer_not`). Libellés :
 * lang/fr (auth.register.*, enums/identity.php, profile.php).
 */
final class RegistrationOptions
{
    /** @var list<string> Pronoms de l'inscription (l'ancien formulaire n'en proposait que trois). */
    public const PRONOUNS = ['elle', 'il', 'iel'];

    /** @var list<string> « Vous êtes ? » : valeur `membre` (membre de la communauté) ou `allie` (personne ressource / allié·e). */
    public const MEMBER_TYPES = ['membre', 'allie'];

    public const OTHER = 'autre';

    private function __construct() {}

    public static function minAge(): int
    {
        return (int) config('xomiton.accounts.min_age', 18);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function pronouns(): array
    {
        return array_map(
            static fn (string $value): array => ['value' => $value, 'label' => (string) __('auth.register.profile.pronouns_options.'.$value)],
            self::PRONOUNS,
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function memberTypes(): array
    {
        return array_map(
            static fn (string $value): array => ['value' => $value, 'label' => (string) __('auth.register.profile.member_types.'.$value)],
            self::MEMBER_TYPES,
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function genderIdentities(): array
    {
        return self::sensitiveOptions(SensitiveProfile::GENDER_IDENTITY, SensitiveProfile::GENDER_IDENTITIES);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function orientations(): array
    {
        return self::sensitiveOptions(SensitiveProfile::ORIENTATION, SensitiveProfile::ORIENTATIONS);
    }

    /**
     * Valeurs acceptées pour l'identité de genre et l'orientation : les listes du profil, plus « je préfère ne pas le dire ».
     *
     * @return list<string>
     */
    public static function sensitiveValues(string $field): array
    {
        return [...SensitiveProfile::options($field), SensitiveProfile::PREFER_NOT];
    }

    public static function memberTypeOf(string $value): MemberType
    {
        return $value === MemberType::Ally->value ? MemberType::Ally : MemberType::Member;
    }

    /**
     * Charge utile de `GET /api/v1/referentials/registration`.
     *
     * @return array{min_age: int, pronouns: list<array{value: string, label: string}>, member_types: list<array{value: string, label: string}>, countries: list<array{value: string, label: string}>, ally_roles: list<array{value: string, label: string}>, gender_identities: list<array{value: string, label: string}>, orientations: list<array{value: string, label: string}>}
     */
    public static function payload(): array
    {
        return [
            'min_age' => self::minAge(),
            'pronouns' => self::pronouns(),
            'member_types' => self::memberTypes(),
            'countries' => Countries::all(),
            'ally_roles' => AllyRole::options(),
            'gender_identities' => self::genderIdentities(),
            'orientations' => self::orientations(),
        ];
    }

    /**
     * @param  list<string>  $values
     * @return list<array{value: string, label: string}>
     */
    private static function sensitiveOptions(string $field, array $values): array
    {
        return array_map(
            static fn (string $value): array => ['value' => $value, 'label' => (string) __('profile.progressive.groups.'.$field.'.options.'.$value)],
            [...$values, SensitiveProfile::PREFER_NOT],
        );
    }
}
