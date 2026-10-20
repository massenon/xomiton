<?php

declare(strict_types=1);

/*
 * Recherche globale d'administration `/admin/recherche` (P10-08 ; prototype admin/recherche.php). Registre : vous (administration).
 * Aucune recherche dans les récits, notes, positions ou contacts (colonnes chiffrées, ADR-33) ; le terme n'est pas journalisé.
 */
return [
    'title' => 'Recherche globale',
    'label' => 'Rechercher dans toute l’administration',
    'placeholder' => 'Nom, e-mail, code de dossier, thématique, annuaire…',
    'submit' => 'Rechercher',
    'help' => 'Utilisateur·rices, dossiers, alertes, annuaire, thématiques, réseau social et journal d’audit. Au moins :min caractères. Raccourci : Ctrl K. Les récits, notes et positions ne sont pas interrogés.',
    'start' => 'Saisissez au moins :min caractères pour lancer la recherche.',
    'too_short' => 'Saisissez au moins :min caractères.',
    'throttled' => 'Trop de recherches en peu de temps. Patientez une minute avant de relancer.',
    'summary' => '{0} Aucun résultat pour « :term ».|{1} 1 résultat pour « :term ».|[2,*] :count résultats pour « :term ».',
    'none' => 'Aucun résultat. Essayez un autre mot-clé ou une autre orthographe.',
    'see_all' => '{1} Voir le résultat dans « :group »|[2,*] Voir les :count résultats dans « :group »',
    'alert_title' => 'Alerte n° :id · :type',
    'group_title' => 'Groupe : :name',
    'groups' => [
        'users' => 'Utilisateur·rices',
        'cases' => 'Dossiers (registres)',
        'alerts' => 'Alertes et signalements',
        'directory' => 'Annuaire',
        'thematiques' => 'Thématiques',
        'network' => 'Réseau social (événements, groupes)',
        'journal' => 'Journal d’audit',
    ],
];
