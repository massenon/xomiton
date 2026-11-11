<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Support;

/**
 * Les trois étapes OBLIGATOIRES de l'inscription (décision client du 11/11/2026), dans UN seul formulaire rendu par le
 * serveur et envoyé une seule fois : « Informations de base », « Profil et identité », « Détails et sécurité ».
 * Le stepper affiche « Étape n sur 3 » ; avec Alpine une étape est visible à la fois, sans JavaScript tout est visible.
 * Chaque champ appartient à une étape : à l'erreur de validation, l'étape du premier champ en erreur est rouverte.
 */
final class RegistrationSteps
{
    /** @var list<string> */
    public const STEPS = ['basic', 'profile', 'details'];

    /** @var array<string, list<string>> étape → champs envoyés (clés de validation) */
    public const FIELDS = [
        'basic' => ['first_name', 'last_name', 'pseudo', 'email', 'age'],
        'profile' => ['pronouns', 'country_code', 'city', 'phone', 'zone_id', 'commune_id', 'member_type'],
        'details' => ['gender_identity', 'gender_identity_other', 'orientation', 'orientation_other', 'sensitive_data', 'ally_role', 'ally_role_other', 'password', 'password_confirmation', 'cgu', 'privacy'],
    ];

    private function __construct() {}

    public static function count(): int
    {
        return count(self::STEPS);
    }

    /** @return list<string> Libellés traduits, dans l'ordre. */
    public static function labels(): array
    {
        return array_map(static fn (string $step): string => (string) __('auth.register.steps.'.$step), self::STEPS);
    }

    /**
     * Numéro (à partir de 1) de l'étape qui porte le premier champ en erreur, dans l'ordre du formulaire ; 1 sans erreur.
     * Une erreur qui ne vient d'aucun champ connu (invitation) ouvre la première étape.
     *
     * @param  list<string>  $errorKeys  clés d'erreur de validation (`pseudo`, `age`…)
     */
    public static function firstInvalid(array $errorKeys): int
    {
        foreach (self::STEPS as $index => $step) {
            if (array_intersect(self::FIELDS[$step], $errorKeys) !== []) {
                return $index + 1;
            }
        }

        return 1;
    }

    /**
     * Métadonnées SEO (lang/fr/seo.php) : `/inscription` est une page publique indexée, une seule URL pour les trois étapes.
     *
     * @return array{title: string, description: string, index: bool}
     */
    public static function seo(): array
    {
        return [
            'title' => (string) __('seo.pages.inscription.title'),
            'description' => (string) __('seo.pages.inscription.description'),
            'index' => true,
        ];
    }
}
