<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Auth\RegisterData;
use App\Enums\AllyRole;
use App\Http\Requests\Support\RegistrationRules;

/**
 * Données de l'inscription en 3 étapes (décision client du 11/11/2026) pour les tests : corps du formulaire web et de
 * l'API (membre par défaut, allié·e avec `allyPayload`), et RegisterData du cas d'usage.
 */
final class RegistrationFixtures
{
    /**
     * Corps valide d'un membre (les trois étapes).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Awa',
            'last_name' => 'Sossou',
            'pseudo' => 'Awa_S',
            'email' => 'Awa@Exemple.bj',
            'age' => 24,
            'pronouns' => 'elle',
            'country_code' => 'BJ',
            'member_type' => 'membre',
            'gender_identity' => 'femme_cis',
            'orientation' => 'lesbienne',
            'sensitive_data' => true,
            'password' => 'Xomiton2026!',
            'password_confirmation' => 'Xomiton2026!',
            'cgu' => true,
            'privacy' => true,
        ], $overrides);
    }

    /**
     * Corps valide d'une personne ressource / allié·e : pas d'identité de genre ni d'orientation, un statut.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function allyPayload(array $overrides = []): array
    {
        $payload = self::payload(['member_type' => 'allie', 'ally_role' => AllyRole::Lawyer->value]);
        unset($payload['gender_identity'], $payload['orientation'], $payload['sensitive_data']);

        return array_merge($payload, $overrides);
    }

    /**
     * RegisterData d'un membre, passé par la même normalisation et le même constructeur que les requêtes.
     *
     * @param  array<string, mixed>  $overrides  clés du corps du formulaire (`first_name`, `age`, `member_type`…)
     */
    public static function data(array $overrides = [], string $ip = '41.79.1.1', string $userAgent = 'Mozilla/5.0 (test)'): RegisterData
    {
        $input = array_merge(self::payload(), $overrides);

        return RegistrationRules::toData(array_merge($input, RegistrationRules::normalize($input)), $ip, $userAgent);
    }
}
