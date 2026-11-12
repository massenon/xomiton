<?php

declare(strict_types=1);

/*
 * Conversations en BD (P20) : textes partagés du rendu des bulles (composant Blade `x-bd.panel` et jumeau Vue
 * `XmBdPanel`), des statuts, des types et des contrôles de la grammaire (ConversationGrammar : import, administration,
 * éditeur des membres au lot B). Exporté vers vue-i18n et Flutter par `php artisan lang:export`. Registre neutre (lu
 * par les lecteurs d'écran et repris dans les écrans publics, membres et d'administration).
 */
return [

    'status' => [
        'brouillon' => 'Brouillon',
        'proposee' => 'Proposée',
        'publiee' => 'Publiée',
        'refusee' => 'Refusée',
        'retiree' => 'Retirée',
    ],

    'types' => [
        'parole' => 'Parole',
        'pensee' => 'Pensée',
        'cri' => 'Cri',
        'tremblee' => 'Voix tremblée',
        'chuchote' => 'Chuchotement',
        'joie' => 'Joie',
        'message' => 'Message écrit',
        'voix_appareil' => 'Voix d’un appareil',
        'cartouche' => 'Cartouche (narration)',
    ],

    'tail_targets' => [
        'personnage' => 'Vers le personnage',
        'ecran' => 'Vers l’écran ou l’appareil',
        'bord-gauche' => 'Hors de l’image, à gauche',
        'bord-droit' => 'Hors de l’image, à droite',
        'bord-haut' => 'Hors de l’image, en haut',
        'bord-bas' => 'Hors de l’image, en bas',
    ],

    // Lecture des bulles dans l'ordre (GRAMMAIRE_DES_BULLES.md §3) : la forme n'est jamais le seul repère.
    'sr' => [
        'verbs' => [
            'parole' => 'dit',
            'pensee' => 'pense',
            'cri' => 'crie',
            'tremblee' => 'dit, mal à l’aise',
            'chuchote' => 'chuchote',
            'joie' => 'dit avec joie',
        ],
        'line' => ':name :verb : :text',
        'line_offscreen' => ':name :verb, hors de l’image : :text',
        'message_received' => 'Message reçu de :name : :text',
        'message_sent' => 'Message envoyé par :name : :text',
        'device' => 'Voix au téléphone, :name : :text',
        'narration' => 'Narration : :text',
        'masked' => 'Un message insultant, masqué : il n’est pas reproduit.',
        'someone' => 'Quelqu’un',
    ],

    'panel' => [
        'counter' => 'Case :n sur :count',
        'title' => 'Case :n sur :count – :title',
        'transcript' => 'Texte de la case',
        'no_bubble' => 'Case sans parole.',
        'image_pending' => 'Illustration à venir',
        'scene' => 'Scène : :description',
    ],

    'grammar' => [
        'where_panel' => 'Case :panel :',
        'where_bubble' => 'Case :panel, bulle :bubble :',
        'panel_count' => 'une conversation compte de :min à :max cases (:count actuellement).',
        'no_bubble' => 'la conversation n’a aucune bulle.',
        'type' => 'type de bulle inconnu.',
        'empty' => 'la bulle est vide.',
        'too_long' => ':count mots : :max au plus. Coupez-la en bulles associées.',
        'too_many' => ':count bulles hors cartouche : :max au plus par case.',
        'outside' => 'la bulle sort de la case (seul le cri peut déborder un peu).',
        'overlap' => 'la bulle chevauche la bulle :other sans être marquée « superposée ».',
        'caption_tail' => 'un cartouche n’a pas de queue.',
        'tail_missing' => 'la queue manque : elle indique qui parle.',
        'tail_target' => 'cible de la queue inconnue.',
        'tail_point' => 'la pointe de la queue doit avoir une position.',
        'tail_outside' => 'la pointe de la queue sort de la case.',
        'tail_inside' => 'la pointe de la queue est dans la bulle.',
        'link' => 'la bulle associée doit suivre une bulle précédente de la même case.',
        'order' => 'ordre de lecture à vérifier : la bulle est placée plus haut et plus à gauche que la précédente.',
        'crowd' => 'plus de 3 bulles superposées : effet de brouhaha, à réserver à cet usage.',
    ],

];
