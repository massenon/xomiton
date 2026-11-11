<?php

declare(strict_types=1);

namespace App\Domain\Account;

/**
 * Champs sensibles du profil (P8-08, RG-02, UX_GUIDELINES.md §4) : identité de genre, orientation (consentement
 * `sensitive_data` requis, colonnes chiffrées `config('xomiton.encrypted_columns.profiles')`) et statut allié·e /
 * personne ressource (chiffré, Q10 c). Règles pures, sans base de données :
 *
 *  - valeurs : clés fermées (libellés dans lang/fr/profile.php `progressive.groups.*.options`), jamais de texte libre ;
 *    `prefer_not` (« Je préfère ne pas le dire ») efface la valeur au lieu d'être enregistré ;
 *  - divulgation : un champ sensible ne sort de la vue « moi » que si la personne l'a **explicitement** ajouté à
 *    `profiles.shared_fields` ; l'identité de genre et l'orientation exigent en plus un consentement en cours.
 */
final class SensitiveProfile
{
    public const GENDER_IDENTITY = 'gender_identity';

    public const ORIENTATION = 'orientation';

    public const ALLY_STATUS = 'ally_status';

    /** Réponse « Je préfère ne pas le dire » : efface la valeur enregistrée. */
    public const PREFER_NOT = 'prefer_not';

    /** @var list<string> */
    public const GENDER_IDENTITIES = ['femme_cis', 'femme_trans', 'homme_cis', 'homme_trans', 'non_binaire', 'intersexe', 'questionnement', 'autre'];

    /** @var list<string> */
    public const ORIENTATIONS = ['lesbienne', 'bisexuelle', 'pansexuelle', 'queer', 'asexuelle', 'heterosexuelle', 'questionnement', 'autre'];

    /** Champs dont l'enregistrement exige le consentement `sensitive_data`. */
    public const CONSENT_REQUIRED = [self::GENDER_IDENTITY, self::ORIENTATION];

    /** Champs qui ne sont montrés aux autres comptes que par choix explicite (`shared_fields`). */
    public const SHAREABLE = [self::GENDER_IDENTITY, self::ORIENTATION, self::ALLY_STATUS];

    private function __construct() {}

    /**
     * Valeurs acceptées pour un champ à liste fermée (identité de genre, orientation).
     *
     * @return list<string>
     */
    public static function options(string $field): array
    {
        return match ($field) {
            self::GENDER_IDENTITY => self::GENDER_IDENTITIES,
            self::ORIENTATION => self::ORIENTATIONS,
            default => [],
        };
    }

    public static function requiresConsent(string $field): bool
    {
        return in_array($field, self::CONSENT_REQUIRED, true);
    }

    /**
     * Liste `shared_fields` nettoyée : champs partageables connus, sans doublon, dans l'ordre de SHAREABLE.
     *
     * @param  array<array-key, mixed>|null  $fields
     * @return list<string>
     */
    public static function normalizeShared(?array $fields): array
    {
        $fields = array_filter((array) $fields, 'is_string');

        return array_values(array_filter(self::SHAREABLE, static fn (string $field): bool => in_array($field, $fields, true)));
    }

    /**
     * Ce qu'un autre compte peut lire des champs sensibles : seulement ceux que la personne a choisi de partager,
     * renseignés, et pour l'identité de genre et l'orientation, tant que le consentement court.
     *
     * @param  array<string, string|null>  $values  champ => valeur déchiffrée
     * @param  array<array-key, mixed>|null  $shared  `profiles.shared_fields`
     * @return array<string, string> champ => valeur
     */
    public static function disclosed(array $values, ?array $shared, bool $consentGranted): array
    {
        $out = [];

        foreach (self::normalizeShared($shared) as $field) {
            $value = $values[$field] ?? null;

            if (! is_string($value) || $value === '' || $value === self::PREFER_NOT) {
                continue;
            }

            if (self::requiresConsent($field) && ! $consentGranted) {
                continue;
            }

            $out[$field] = $value;
        }

        return $out;
    }
}
