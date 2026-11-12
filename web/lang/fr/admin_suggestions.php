<?php

declare(strict_types=1);

/*
 * Administration, Boîte à suggestions `/admin/suggestions` (P18-14). Registre : vous (VOICE_AND_TONE.md §3). La réponse
 * de l'équipe, envoyée à la personne, se rédige au tutoiement comme tout texte adressé aux membres.
 */
return [

    'title' => 'Suggestions',
    'intro' => 'Les idées des membres, envoyées depuis leur espace et l’application. Une suggestion anonyme n’affiche jamais son compte. Votre réponse est envoyée en notification à la personne : écrivez-la au tutoiement.',
    'list_title' => 'Boîte à suggestions',
    'new_count' => '{0} Aucune nouvelle suggestion|{1} :count nouvelle suggestion|[2,*] :count nouvelles suggestions',
    'search_placeholder' => 'Mot du message…',
    'all_statuses' => 'Tous les statuts',
    'all_categories' => 'Toutes les catégories',
    'caption' => 'Suggestions : message, catégorie, auteur·rice, date, statut et actions.',
    'empty' => 'Aucune suggestion',
    'anonymous' => 'Anonyme',
    'deleted_account' => 'Compte supprimé',
    'replied' => 'Réponse envoyée',
    'sr_row' => 'suggestion n° :id',
    'modal_title' => 'Suggestion',
    'reply_help' => 'Envoyée en notification à la personne, même si elle est restée anonyme.',
    'reply_sent' => 'Dernière réponse envoyée le :date. Une nouvelle réponse remplace la précédente et part en notification.',
    'columns' => ['message' => 'Message', 'category' => 'Catégorie', 'author' => 'Auteur·rice', 'created' => 'Reçue le', 'status' => 'Statut'],
    'fields' => ['status' => 'Statut', 'reply' => 'Réponse de l’équipe'],
    'actions' => ['open' => 'Lire', 'save_status' => 'Changer le statut', 'reply' => 'Envoyer la réponse'],
    'toasts' => [
        'status' => 'Statut : :status.',
        'replied' => 'Réponse envoyée.',
    ],
];
