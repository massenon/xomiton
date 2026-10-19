<?php

declare(strict_types=1);

/*
 * Recherche membre `/recherche` et suggestions du champ de l'en-tête (P8-13, lot recherche-membre ; prototype
 * partials/header.php l.30-33), espace connecté : tutoiement (VOICE_AND_TONE.md §3). Exporté vers vue-i18n par
 * `php artisan lang:export`. Aucun texte ne recopie le terme cherché.
 */
return [
    'title' => 'Recherche',
    'heading' => 'Rechercher',
    'intro' => 'Ressources, thématiques, contacts de l’annuaire et membres du réseau.',
    'privacy' => 'Xomiton n’enregistre pas tes recherches. Ton navigateur garde l’adresse de cette page dans son historique : sur un appareil partagé, efface-le ou active le mode discret.',
    'privacy_discreet' => 'Xomiton n’enregistre pas tes recherches. Mode discret : le mot cherché n’apparaît pas dans l’adresse de la page.',

    'form' => [
        'label' => 'Rechercher',
        'placeholder' => 'Rechercher une ressource, un contact…',
        'submit' => 'Rechercher',
        'hint' => 'Au moins :min caractères.',
    ],

    'types' => [
        'all' => 'Tout',
        'label' => 'Familles de résultats',
        'ressources' => 'Ressources',
        'thematiques' => 'Thématiques',
        'contacts' => 'Contacts',
        'membres' => 'Membres',
    ],

    'results' => '{0} Aucun résultat|{1} 1 résultat|[2,*] :count résultats',
    'results_unit' => '{0} résultat|{1} résultat|[2,*] résultats',
    'see_all_named' => '{1} Voir le résultat dans « :type »|[2,*] Voir les :count résultats dans « :type »',
    'back_all' => 'Toutes les familles',
    'loaded' => '{1} 1 résultat de plus affiché.|[2,*] :count résultats de plus affichés.',

    'start' => 'Écris au moins :min caractères pour lancer la recherche.',
    'too_short' => 'Écris au moins :min caractères.',
    'empty' => 'Aucun résultat. Essaie un autre mot, ou parcours les ressources.',
    'empty_section' => 'Aucun résultat dans cette famille.',
    'browse' => 'Parcourir les ressources',
    'failed' => 'La recherche est momentanément indisponible. Réessaie dans un instant.',

    'hit' => [
        'reading' => ':count min de lecture',
        'directory' => 'Voir dans l’annuaire',
    ],

    'suggest' => [
        'label' => 'Suggestions',
        'loading' => 'Recherche en cours…',
        'none' => 'Aucune suggestion.',
        'see_all' => 'Voir tous les résultats',
        'count' => '{0} Aucune suggestion.|{1} 1 suggestion, flèches haut et bas pour parcourir.|[2,*] :count suggestions, flèches haut et bas pour parcourir.',
        'offline' => 'Hors connexion : les suggestions reviendront avec le réseau.',
    ],

    'errors' => [
        'unavailable' => 'La recherche est momentanément indisponible. Réessaie dans un instant.',
    ],
];
