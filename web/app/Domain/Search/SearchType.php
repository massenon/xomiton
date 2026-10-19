<?php

declare(strict_types=1);

namespace App\Domain\Search;

/**
 * Familles de résultats de la recherche membre (P8-13, en-tête XmHeader « Rechercher une ressource, un contact… » ;
 * prototype partials/header.php l.30-33), dans leur ordre d'affichage ; la valeur est celle de `?type=` (page
 * `/recherche` et `GET /api/v1/search`). Chaque famille ne cherche que des colonnes **en clair** (ADR-33) :
 *  - ressources du catalogue : titre, résumé ;
 *  - thématiques publiées : titre, résumé ;
 *  - contacts de l'annuaire selon la portée du compte (DirectoryReach) : nom, organisation, libellé du rôle, commune ;
 *  - membres **trouvables** (réglage « Trouvable par recherche », P8-09 ; NetworkPeople::discoverableBy) : pseudonyme,
 *    jamais le nom réel.
 * Les contacts exigent `directory.view`, les membres l'accès au réseau (`social.access`) : `availableTo()`.
 */
enum SearchType: string
{
    case Resources = 'ressources';
    case Thematiques = 'thematiques';
    case Contacts = 'contacts';
    case Members = 'membres';

    /**
     * Familles ouvertes au compte, dans l'ordre d'affichage.
     *
     * @return list<self>
     */
    public static function availableTo(bool $canBrowseDirectory, bool $canSeeNetwork): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $type): bool => match ($type) {
                self::Contacts => $canBrowseDirectory,
                self::Members => $canSeeNetwork,
                default => true,
            },
        ));
    }

    public function label(): string
    {
        return (string) __('search.types.'.$this->value);
    }

    /** Icône Bootstrap Icons de la famille (en-têtes de section, suggestions). */
    public function icon(): string
    {
        return match ($this) {
            self::Resources => 'bi-folder2-open',
            self::Thematiques => 'bi-journal-text',
            self::Contacts => 'bi-person-vcard',
            self::Members => 'bi-people',
        };
    }
}
