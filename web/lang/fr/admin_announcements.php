<?php

declare(strict_types=1);

/*
 * Administration, Annonce globale `/admin/annonces` (P18-13, Q97). Registre : vous (VOICE_AND_TONE.md §3, administration).
 * La bannière n'apparaît que dans l'espace membre web et l'application, jamais sur le site public.
 */
return [

    'title' => 'Annonces',
    'intro' => 'Une annonce s’affiche en bannière en haut de l’espace membre et de l’application, pendant la période choisie. Elle n’apparaît jamais sur le site public ni pour les visiteur·ses. Chaque personne peut la fermer : elle ne revient plus pour elle. Le bandeau de maintenance reste distinct.',
    'list_title' => 'Toutes les annonces',
    'create' => 'Nouvelle annonce',
    'create_title' => 'Nouvelle annonce',
    'edit_title' => 'Modifier l’annonce',
    'save' => 'Enregistrer',
    'caption' => 'Annonces : titre, public, période, état et actions.',
    'empty' => 'Aucune annonce',
    'from' => 'Du :date',
    'until' => 'au :date',
    'no_end' => 'sans date de fin',
    'columns' => ['title' => 'Annonce', 'audience' => 'Public', 'period' => 'Période', 'state' => 'État'],
    'states' => ['programmee' => 'Programmée', 'en_cours' => 'En cours', 'terminee' => 'Terminée'],
    'fields' => [
        'title' => 'Titre court',
        'message' => 'Message',
        'link_path' => 'Lien interne (facultatif)',
        'level' => 'Niveau',
        'audience' => 'Public',
        'starts_at' => 'Début',
        'ends_at' => 'Fin (facultative)',
    ],
    'help' => [
        'title' => ':max caractères au plus.',
        'message' => ':max caractères au plus, au tutoiement comme le reste de l’espace membre.',
        'link_path' => 'Une adresse de la plateforme qui commence par « / », par exemple /forums ou /evenements. Jamais un site extérieur.',
        'audience' => 'Les pair·es seulement : comptes qui détiennent le rôle pair animateur·rice.',
        'starts_at' => 'Heure du Bénin.',
        'ends_at' => 'Heure du Bénin. Sans date de fin, l’annonce reste jusqu’à ce que vous la terminiez.',
    ],
    'actions' => ['edit' => 'Modifier', 'end' => 'Terminer', 'delete' => 'Supprimer'],
    'confirm_end' => 'Terminer « :title » maintenant ? La bannière disparaît pour tout le monde.',
    'confirm_delete' => 'Supprimer « :title » ? Cette action ne peut pas être annulée.',
    'toasts' => [
        'created' => 'Annonce créée.',
        'saved' => 'Annonce enregistrée.',
        'ended' => 'Annonce terminée.',
        'deleted' => 'Annonce supprimée.',
    ],
];
