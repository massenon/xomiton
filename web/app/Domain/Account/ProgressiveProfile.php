<?php

declare(strict_types=1);

namespace App\Domain\Account;

/**
 * Profil progressif (P8-08, `authentification.md` l.9, LOGIN_INSCRPTION_MULTISTEP_USER_INSTRUCTION.md §7) : groupes de
 * questions facultatives proposés **un à la fois** par le bandeau « Complète ton profil, c'est facultatif ». Règle pure :
 * un groupe est traité s'il est renseigné ou si la personne y a déjà répondu (y compris « Je préfère ne pas le dire »,
 * `profiles.progressive_answered`) ; le bandeau propose le premier groupe non traité, et disparaît quand il n'y en a plus.
 */
final class ProgressiveProfile
{
    /** @var list<string> Ordre de la ligne P8-08 du plan. */
    public const GROUPS = ['pronouns', 'member_type', 'ally_status', 'gender_identity', 'orientation', 'age', 'location', 'interests'];

    /** Groupes dont les réponses sont sensibles (consentement `sensitive_data`). */
    public const SENSITIVE_GROUPS = ['gender_identity', 'orientation'];

    /** Champ du PATCH me/profile → groupe qu'il traite. */
    private const FIELD_GROUPS = [
        'pronouns' => 'pronouns',
        'member_type' => 'member_type',
        'ally_status' => 'ally_status',
        'gender_identity' => 'gender_identity',
        'orientation' => 'orientation',
        'age_range' => 'age',
        'zone_id' => 'location',
        'commune_id' => 'location',
        'interests' => 'interests',
    ];

    private function __construct() {}

    public static function groupOf(string $field): ?string
    {
        return self::FIELD_GROUPS[$field] ?? null;
    }

    /**
     * @param  array<array-key, mixed>|null  $groups
     * @return list<string> groupes connus, sans doublon, dans l'ordre de GROUPS
     */
    public static function normalize(?array $groups): array
    {
        $groups = array_filter((array) $groups, 'is_string');

        return array_values(array_filter(self::GROUPS, static fn (string $group): bool => in_array($group, $groups, true)));
    }

    /**
     * @param  array<string, bool>  $filled  groupe => renseigné ?
     * @param  array<array-key, mixed>|null  $answered  `profiles.progressive_answered`
     * @return array{groups: list<array{key: string, answered: bool}>, next: string|null, done: bool, answered: int, total: int}
     */
    public static function state(array $filled, ?array $answered): array
    {
        $answered = self::normalize($answered);
        $groups = [];
        $next = null;
        $count = 0;

        foreach (self::GROUPS as $group) {
            $done = ! empty($filled[$group]) || in_array($group, $answered, true);
            $groups[] = ['key' => $group, 'answered' => $done];

            if ($done) {
                $count++;
            } elseif ($next === null) {
                $next = $group;
            }
        }

        return ['groups' => $groups, 'next' => $next, 'done' => $next === null, 'answered' => $count, 'total' => count(self::GROUPS)];
    }
}
