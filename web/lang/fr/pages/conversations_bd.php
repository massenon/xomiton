<?php

declare(strict_types=1);

/*
 * Conversations en BD (P20-06, P20-10) : liste publique `/conversations-bd`, page d'une conversation
 * `/conversations-bd/{slug}` et mode séance `/conversations-bd/{slug}/seance`. Registre : vous (site public,
 * VOICE_AND_TONE.md §3) ; jamais « victime » ; « pair animateur·rice ».
 */
return [

    'title' => 'Conversations en BD',
    'h1' => 'Conversations en BD : ce qui se dit, ce qui se pense',
    'lead' => 'Des histoires courtes en cases illustrées, où les personnages parlent, pensent, crient ou chuchotent dans des bulles. L’écart entre ce qui est dit et ce qui est pensé aide à comprendre une situation et à oser en parler.',
    'meta_title' => 'Conversations en BD : dire, penser, oser parler',
    'meta_description' => 'Des BD où les personnages parlent en bulles : consentement, pression du groupe, travail, handicap, coming out. À lire seul·e ou en séance, avec des liens d’aide.',
    'meta_description_filler' => 'Conversation en BD Xomiton à lire en ligne, seul·e ou en séance, avec des liens d’aide au Bénin.',
    'meta_title_show' => 'Conversation en BD « :title »',
    'genre' => 'Bande dessinée',
    'list_label' => 'Liste des conversations en BD',
    'count' => '{0} Aucune conversation pour l’instant.|{1} :count conversation|[2,*] :count conversations',
    'empty' => 'Les conversations en BD arrivent bientôt.',
    'cover_alt' => 'Première case de la conversation « :title »',
    'panels_count' => '{1} :count case|[2,*] :count cases',
    'read' => 'Lire la conversation',
    'kicker' => 'Conversation en BD',
    'theme' => 'Thème : :theme',
    'audience' => 'Public conseillé : :audience',
    'comics_link' => 'Les BD sur les violences numériques',

    // Page d'une conversation
    'warning_sos_link' => 'Alerte SOS',
    'discreet_title' => 'Mode discret activé',
    'discreet_text' => 'Les cases sont masquées tant que vous ne choisissez pas de les afficher.',
    'discreet_show' => 'Afficher la conversation',
    'key_message_title' => 'À retenir',
    'help_title' => 'Besoin d’aide ?',
    'help_text' => 'Si cette situation vous concerne, vous n’êtes pas seul·e. Une personne formée peut vous écouter.',
    'help_report' => 'Faire un signalement',
    'help_sos' => 'Alerte SOS',
    'help_emergency' => 'Numéros d’urgence',
    'help_center' => 'Centre d’aide',
    'others_title' => 'D’autres conversations',
    'all_conversations' => 'Toutes les conversations en BD',
    'seance_title' => 'Animer une séance',
    'seance_text' => 'Pour les pair·es animateur·rices : projetez la conversation case par case, révélez les pensées puis lancez la discussion.',
    'seance_open' => 'Ouvrir le mode séance',
    'questions_title' => 'Questions pour en parler',

    'viewer' => [
        'region' => 'Cases de la conversation « :title »',
        'roledescription' => 'visionneuse de BD',
        'keyboard_help' => 'Utilisez les boutons ou les flèches gauche et droite du clavier pour changer de case.',
        'counter' => 'Case :n sur :count',
        'previous' => 'Case précédente',
        'next' => 'Case suivante',
    ],

    'seance' => [
        'meta_title' => 'Mode séance – :title',
        'kicker' => 'Mode séance',
        'back' => 'Revenir à la conversation',
        'fullscreen' => 'Plein écran',
        'exit_fullscreen' => 'Quitter le plein écran',
        'reveal' => 'Révéler les pensées',
        'hide' => 'Masquer les pensées',
        'revealed' => 'Pensées révélées.',
        'thoughts_hidden' => 'Les pensées sont masquées : faites d’abord parler le groupe sur ce qui est dit.',
        'previous' => 'Précédent',
        'next' => 'Suivant',
        'questions' => 'Questions',
        'counter' => 'Case :n sur :count',
        'questions_counter' => 'Questions d’animation',
        'keyboard_help' => 'Clavier ou télécommande : flèche droite, Page suivante, Espace ou Entrée pour avancer (les pensées d’abord) ; flèche gauche ou Page précédente pour revenir ; P pour les pensées ; F pour le plein écran.',
        'questions_title' => 'Questions d’animation',
        'key_message' => 'Message clé : :message',
        'no_questions' => 'Pas de questions pour cette conversation : laissez le groupe réagir librement.',
        'restart' => 'Recommencer',
    ],

];
