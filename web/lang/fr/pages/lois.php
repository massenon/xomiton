<?php

declare(strict_types=1);

/*
 * Lois et codes du Bénin `/lois` (P4-04 ; prototype_social/lois-benin.php). Registre : vous (page publique
 * institutionnelle « Apprendre », VOICE_AND_TONE.md §3, Q36). Les textes de loi viennent de la table `laws`
 * (résumé + version simple `simple_summary`, plan §7) ; recherche et catégorie filtrées dans le navigateur,
 * préférence « Lire en version simple » gardée sur l'appareil (localStorage `xm_law_simple`).
 */
return [

    'title' => 'Lois et codes du Bénin',
    'intro' => 'Connaître vos droits pour les faire respecter.',

    // Outils (prototype l.36-46), révélés avec JavaScript
    'tools_label' => 'Rechercher et filtrer les textes',
    'simple_switch' => 'Lire en version simple',
    'simple_hint' => 'Préférence gardée sur cet appareil.',
    'search_label' => 'Rechercher un texte',
    'search_placeholder' => 'Rechercher (viol, harcèlement, VIH…)',
    'category_label' => 'Catégorie',
    'category_all' => 'Toutes les catégories',
    'count_none' => 'Aucun texte affiché.',
    'count_one' => '1 texte affiché.',
    'count_many' => ':count textes affichés.',

    // Liste (prototype l.49-70)
    'list_title' => 'Textes de loi',
    'legal_label' => 'Résumé',
    'simple_label' => 'Version simple',
    'official' => 'Lire le texte officiel',
    'official_hint' => 'site externe',
    'urgent' => 'Contacts d’urgence',
    'jurist' => 'Parler à un·e juriste',
    'disclaimer' => 'Résumé indicatif sans valeur juridique. Pour votre situation, consultez un·e juriste.',
    'empty' => 'Aucun texte ne correspond à votre recherche.',
    'clear' => 'Effacer la recherche',
    'none' => 'Aucun texte de loi publié pour le moment.',

    'og_alt' => 'Lois et codes du Bénin : résumés des textes qui protègent vos droits',
];
