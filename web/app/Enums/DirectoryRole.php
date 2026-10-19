<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Rôles de l'annuaire (DATABASE_ENGINEERING.md §3 `directory_contacts`), utilisés aussi par
 * `needs.routed_role` pour le routage des alertes (RG-04).
 */
enum DirectoryRole: string
{
    case PointFocal = 'point_focal';
    case Juriste = 'juriste';
    case Psychologue = 'psychologue';
    case Gynecologue = 'gynecologue';
    case ResponsableReseau = 'responsable_reseau';
    case PairAnimateur = 'pair_animateur';
    case TravailleurSocial = 'travailleur_social';
    case PersonnelIdentifie = 'personnel_identifie';

    public function label(): string
    {
        return (string) __('enums/reference.directory_role.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::PointFocal => 'danger',
            self::Juriste => 'info',
            self::Psychologue => 'primary',
            self::Gynecologue => 'success',
            self::ResponsableReseau => 'warning',
            self::PairAnimateur => 'violet',
            self::TravailleurSocial, self::PersonnelIdentifie => 'secondary',
        };
    }

    /**
     * Couleur du badge côté membre et public (`/annuaire`, `/urgences`, API `directory` ; Q62) : le rouge reste réservé
     * à l'urgence et le gris se confond avec le texte secondaire, d'où la couleur primaire pour le point focal et les
     * rôles `secondary`. `badge()` garde la couleur du prototype pour l'administration.
     */
    public function memberBadge(): string
    {
        $badge = $this->badge();

        return in_array($badge, ['danger', 'secondary'], true) ? 'primary' : $badge;
    }

    /** Rôles proposés comme cible d'un besoin (les autres relèvent du point focal). */
    public function isRoutable(): bool
    {
        return in_array($this, [self::Juriste, self::Psychologue, self::Gynecologue], true);
    }

    /**
     * Retrouve un rôle à partir du libellé du prototype (« Pair animateur·rice », « Point focal »…),
     * pour l'import de `annuaire.json` et des seeders.
     */
    public static function tryFromLabel(string $label): ?self
    {
        foreach (self::cases() as $case) {
            if (mb_strtolower(trim($label)) === mb_strtolower($case->label())) {
                return $case;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(static fn (self $case): string => $case->label(), self::cases()),
        );
    }
}
