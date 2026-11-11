<?php

declare(strict_types=1);

/*
 * Ressources publiques `/ressources`, `/ressources/{slug}` et `/ressources/{slug}/telecharger` (P4-03 ; prototype_social/
 * ressources.php, ressource.php). Registre : vous (site public institutionnel, VOICE_AND_TONE.md §3, Q36). Pages
 * publiques indexables (Q19 a). Toasts hors connexion : microcopy.offline.* (neutres) ; jamais « 24 h/24 » sans
 * « réponse humaine sous 24 h » : l'assistant public n'enregistre rien et ne mène à aucun humain (Q55).
 */
return [

    'title' => 'Ressources',
    'intro' => 'Articles, fiches pratiques et outils à lire, à enregistrer et à partager.',
    'aside_label' => 'Compléments et aide',

    // En-tête, recherche, filtres (prototype l.40-55)
    'offline_count' => '{0} Aucune ressource hors connexion|{1} :count disponible hors connexion|[2,*] :count disponibles hors connexion',
    // Nom accessible stable du bouton-bascule, suivi du compteur visible (le bouton porte aria-pressed).
    'offline_filter_label' => 'Afficher seulement les ressources enregistrées',
    'search_label' => 'Rechercher une ressource',
    'search_placeholder' => 'Rechercher une ressource…',
    'type_label' => 'Type de ressource',
    'type_all' => 'Tous les types',
    'filters_label' => 'Filtrer par catégorie',
    'filter_all' => 'Tout',
    'category_short' => [
        'vbg' => 'VBG',
    ],
    'reset_filters' => 'Réinitialiser les filtres',

    // Liste (prototype l.58-80)
    'results' => '{0} Aucune ressource|{1} :count ressource|[2,*] :count ressources',
    'results_query' => '{0} Aucune ressource pour « :query »|{1} :count ressource pour « :query »|[2,*] :count ressources pour « :query »',
    'cover_alt' => 'Couverture de la ressource : :title',
    'og_alt_index' => 'Ressources Xomiton : fiches, guides, vidéos et documents',
    'minutes' => '{1} :count min|[2,*] :count min',
    'offline_switch' => 'Hors ligne',
    'offline_switch_hint' => ': rendre « :title » disponible hors connexion',
    'offline_only_empty' => 'Aucune ressource n’est enregistrée pour le hors connexion sur cet appareil.',
    'offline_list_title' => 'Ressources enregistrées pour le hors connexion',
    'offline_remove' => 'Retirer',
    'offline_remove_hint' => ' « :title » du hors connexion',
    'shown_of' => ':shown sur :total',
    'shown_capped' => 'Affichage limité à :shown ressources sur :total. Affinez la recherche ou choisissez un type ou une catégorie pour voir les autres.',
    'end_of_list' => '{1} Vous avez tout vu : :count élément affiché.|[2,*] Vous avez tout vu : :count éléments affichés.',
    'empty_title' => 'Aucune ressource ne correspond à votre recherche.',
    'empty_text' => 'Essayez un autre mot, un autre type ou retirez le filtre de catégorie.',
    'empty_none' => 'Les ressources arrivent bientôt.',

    // Fiches à télécharger (prototype l.82-93)
    'files_title' => 'Fiches à télécharger',
    'file_meta' => ':kind · :size · :category',
    'file_meta_no_category' => ':kind · :size',
    'download_hint' => '« :title » (:kind, :size)',

    // FAQ de la catégorie et CTA assistant (FRONT_END_SECTION_ORDER_INSTRUCTION.md)
    'faq_title' => 'Questions fréquentes : :category',
    'assistant_title' => 'Une question ? L’assistant Xomiton vous répond',
    'assistant_text' => 'L’assistant répond en ligne 24 h/24, sans compte, sur les violences, la santé sexuelle ou vos droits. Pour une réponse humaine sous 24 h, exprimez un besoin.',
    'assistant_need' => 'Exprimer un besoin',
    'signup_text' => 'Créez un compte pour retrouver la communauté, vos groupes et les nouveautés.',

    // Fiche /ressources/{slug}
    'published_on' => 'Publié le :date',
    'downloads' => '{0} Aucun téléchargement|{1} :count téléchargement|[2,*] :count téléchargements',
    'download_cta' => 'Télécharger (:kind, :size)',
    'download_cta_no_size' => 'Télécharger (:kind)',
    'open_external' => 'Ouvrir le lien',
    'media_title' => 'Lire la ressource',
    'media_unsupported' => 'Votre navigateur ne peut pas lire ce fichier. Téléchargez-le pour l’ouvrir.',
    'transcript_title' => 'Transcription',
    'legal_notice' => 'Ces informations sont éducatives et ne remplacent pas un conseil juridique personnalisé. Consultez un·e juriste de l’annuaire pour votre situation.',
    'offline_title' => 'Lecture hors connexion',
    'offline_text' => 'Activez l’interrupteur pour garder cette ressource sur cet appareil.',
    'thematique_title' => 'Thématique liée',
    'related_title' => 'Ressources de la même catégorie',
    'reading_minutes' => '{1} :count min de lecture|[2,*] :count min de lecture',
    'help_title' => 'Besoin d’aide ?',
    'link_emergency' => 'Contacts d’urgence',
    'link_report' => 'Signaler une violence',
    'link_sos' => 'Alerte SOS avec localisation',
    'meta_description_filler' => 'Ressource Xomiton pour les filles et femmes LBTQ+ au Bénin.',

];
