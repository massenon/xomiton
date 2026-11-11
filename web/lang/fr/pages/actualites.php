<?php

declare(strict_types=1);

/*
 * Actualités publiques `/actualites`, `/actualites/{slug}` (P4-10 ; prototype_social/blog.php, blog-details.php).
 * Registre : vous (site public institutionnel, VOICE_AND_TONE.md §3, Q36). Actualités = ressources `kind = article`
 * (Q1 a). Signature unique « Équipe Xomiton » : aucun nom de personne publié (plan §7).
 */
return [

    'title' => 'Actualités',
    'intro' => 'Articles, guides et nouvelles de la communauté Xomiton.',
    'aside_label' => 'Compléments',
    'og_alt_index' => 'Actualités Xomiton : articles, guides et nouvelles de la communauté',

    // Filtres (prototype blog.php l.14-17)
    'filters_label' => 'Filtrer par catégorie',
    'filter_all' => 'Tout',

    // Liste (prototype l.20-36)
    'list_title' => 'Liste des actualités',
    'cover_alt' => 'Couverture de l’article : :title',
    'author_team' => 'Équipe Xomiton',
    'minutes' => '{1} :count min|[2,*] :count min',
    'empty_none' => 'Aucune actualité publiée pour le moment.',
    'empty_category' => 'Aucun article dans cette catégorie.',
    'show_all' => 'Voir toutes les actualités',
    'shown_of' => ':shown sur :total',
    'shown_capped' => ':shown actualités affichées sur :total. Filtrez par catégorie pour affiner.',
    'end_of_list' => '{1} Vous avez vu la seule actualité.|[2,*] Vous avez vu les :count actualités.',

    // Colonne (prototype l.38-58)
    'events_title' => 'Prochains événements',
    'events_empty' => 'Aucun événement à venir pour le moment.',
    'events_online' => 'En ligne',
    'question_title' => 'Une question ?',
    'question_text' => 'Consultez le centre d’aide ou discutez avec l’assistant.',
    'question_cta' => 'Centre d’aide',
    'question_alt' => 'Illustration : un personnage souriant du centre d’aide Xomiton',

    // Fiche (prototype blog-details.php l.31-63)
    'reading_time' => '{1} :count min de lecture|[2,*] :count min de lecture',
    'published_on' => 'Publié le :date',
    'disclaimer' => 'Ce contenu informe mais ne remplace pas l’avis d’un·e professionnel·le. En cas de danger, utilisez le :sos ou les :urgences.',
    'disclaimer_sos' => 'SOS',
    'disclaimer_urgences' => 'contacts d’urgence',
    'back' => 'Toutes les actualités',
    'share' => 'Partager',
    'share_copied' => 'Lien copié.',
    'share_failed' => 'Copie impossible. Copiez l’adresse de la page.',
    'others_title' => 'À lire aussi',
    'meta_description_filler' => 'Actualité Xomiton pour les filles et femmes LBTQ+ au Bénin.',
];
