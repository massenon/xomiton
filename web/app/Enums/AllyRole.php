<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Statut d'une personne qui s'inscrit comme « Personne ressource / Allié·e » (inscription en 3 étapes, décision client du
 * 11/11/2026) : `profiles.ally_role` (chiffré). La précision d'un statut « autre » est `profiles.ally_role_other`.
 * Distinct de AllyStatus (profil progressif) : aucun droit n'y est associé.
 */
enum AllyRole: string
{
    case HealthProfessional = 'professionnel_sante';
    case Lawyer = 'avocat';
    case Psychologist = 'psychologue';
    case ReligiousLeader = 'leader_religieux';
    case Activist = 'activiste';
    case Other = 'autre';

    public function label(): string
    {
        return (string) __('enums/identity.ally_role.'.$this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
