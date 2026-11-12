<?php

declare(strict_types=1);

/*
 * Bandes dessinées publiques `/bd` et `/bd/{key}` (P19-04, Q19 : BD `is_public` seulement) et visionneuse
 * `x-comic-viewer`. Registre : vous (site public). Publication du fil : compte officiel (P19-11).
 */
return [

    'title' => 'Bandes dessinées',
    'h1' => 'Bandes dessinées : comprendre les violences numériques',
    'lead' => 'Des histoires courtes, en quatre planches, pour reconnaître une situation, savoir quoi faire et trouver de l’aide. À lire seul·e, en groupe ou à partager.',
    'meta_title' => 'BD sur les violences numériques à lire et partager',
    'meta_description' => 'Des bandes dessinées courtes pour reconnaître le harcèlement en ligne, la sextorsion, la traque ou les arnaques, réagir et trouver de l’aide au Bénin.',
    'meta_description_filler' => 'Bande dessinée Xomiton à lire en ligne et à partager, avec la fiche « reconnaître, réagir, se faire aider ».',
    'list_label' => 'Liste des bandes dessinées',
    'count' => '{0} Aucune BD pour l’instant.|{1} :count bande dessinée|[2,*] :count bandes dessinées',
    'empty' => 'Les bandes dessinées arrivent bientôt.',
    'cover_alt' => 'Couverture de la BD « :title »',
    'pages_count' => '{1} :count planche|[2,*] :count planches',
    'read' => 'Lire la BD',
    'topic' => 'Sujet : :title',
    'kicker' => 'BD Xomiton',
    'meta_title_show' => 'BD « :title » : :topic',
    'official_account_bio' => 'Compte officiel de la plateforme Xomiton : actualités, BD et fiches pour s’informer et se protéger.',

    // Page d'une BD
    'warning_sos_link' => 'Alerte SOS',
    'discreet_title' => 'Mode discret activé',
    'discreet_text' => 'Les planches sont masquées tant que vous ne choisissez pas de les afficher.',
    'discreet_show' => 'Afficher la BD',
    'after_title' => 'Et maintenant ?',
    'after_text' => 'La fiche vous explique les signes, les bons réflexes et où trouver de l’aide.',
    'after_fiche' => 'Lire la fiche « :title »',
    'help_title' => 'Besoin d’aide ?',
    'help_report' => 'Faire un signalement',
    'help_sos' => 'Alerte SOS',
    'help_emergency' => 'Numéros d’urgence',
    'others_title' => 'D’autres BD',
    'all_comics' => 'Toutes les BD',
    'print' => 'Imprimer la BD',

    'viewer' => [
        'region' => 'Planches de la BD « :title »',
        'roledescription' => 'visionneuse de BD',
        'keyboard_help' => 'Utilisez les boutons ou les flèches gauche et droite du clavier pour changer de planche.',
        'counter' => 'Planche :n sur :count',
        'previous' => 'Planche précédente',
        'next' => 'Planche suivante',
        'alt_fallback' => ':title – planche :n',
        'page_title' => 'Planche :n sur :count – :title',
    ],

];
