<?php

declare(strict_types=1);

/*
 * Thématiques publiques `/thematiques` et `/thematiques/{slug}` (P4-02 ; prototype_social/thematiques.php, thematique.php,
 * inc/x_thematiques_content.php). Registre : vous (site public institutionnel, VOICE_AND_TONE.md §3, Q36).
 * Pages publiques indexables (Q19 a) ; « Assistant Xomiton » sans promesse « 24 h/24 » (Q36).
 */
return [

    'title' => 'Thématiques',
    'count' => '{0} Aucune fiche pour l’instant.|{1} :count fiche pédagogique pour comprendre, se protéger et agir.|[2,*] :count fiches pédagogiques pour comprendre, se protéger et agir.',
    'search_label' => 'Rechercher une thématique',
    'search_placeholder' => 'Rechercher une thématique…',
    'filters_label' => 'Filtrer par catégorie',
    'filter_all' => 'Toutes (:count)',
    'list_title' => 'Liste des fiches',
    'cover_alt' => 'Couverture de la fiche : :title',
    'og_alt_index' => 'Fiches thématiques Xomiton',
    'reading_minutes' => '{1} :count min de lecture|[2,*] :count min de lecture',
    'minutes' => '{1} :count min|[2,*] :count min',
    'shown_of' => ':shown sur :total',
    'end_of_list' => '{1} Vous avez tout vu : :count élément affiché.|[2,*] Vous avez tout vu : :count éléments affichés.',
    'shown_capped' => 'Affichage limité à :shown fiches sur :total. Affinez la recherche ou choisissez une catégorie pour voir les autres.',
    'empty_title' => 'Aucune thématique ne correspond à votre recherche.',
    'empty_text' => 'Essayez un autre mot ou retirez le filtre de catégorie.',
    'empty_none' => 'Les fiches arrivent bientôt.',

    // Fiche
    'kicker' => 'Fiche pédagogique Xomiton',
    'warning_sos_link' => 'Alerte SOS',
    'legal_notice' => 'Ces informations sont éducatives et ne remplacent pas un conseil juridique personnalisé. Consultez un·e juriste de l’annuaire pour votre situation.',
    'faq_title' => 'Questions fréquentes',
    'aside_label' => 'Aide et fiches liées',
    'help_title' => 'Besoin d’aide ?',
    'contacts_label' => 'Contacts de l’annuaire',
    'call' => 'Appeler',
    'link_directory' => 'Annuaire : points focaux, juristes, psychologues',
    'link_emergency' => 'Contacts d’urgence',
    'link_sos' => 'Alerte SOS avec localisation',
    'link_assistant' => 'Assistant Xomiton',
    'resource_title' => 'Ressource principale',
    'related_title' => 'Thématiques liées',
    'meta_description_filler' => 'Fiche Xomiton pour les filles et femmes LBTQ+ au Bénin.',

];
