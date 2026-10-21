<?php

declare(strict_types=1);

/*
 * Administration, Passerelles réseaux `/admin/passerelles` (P12-08, D4-bis, ADR-15 ; prototype admin/passerelles.php).
 * Registre : vous, neutre et précis (VOICE_AND_TONE.md §3, administration). Les libellés des réseaux, types et statuts sont
 * dans enums/gateways.php. Aucun jeton n’est jamais affiché ni écrit au journal.
 */
return [

    'title' => 'Passerelles réseaux',
    'notice' => 'Aucun connecteur automatique n’est encore branché : les publications sont préparées ici, puis publiées à la main sur le compte de l’organisation et marquées « publiées ». Aucune donnée de membre n’est envoyée aux réseaux.',
    'cards_title' => 'Comptes de l’organisation',
    'not_connected' => 'Non connecté',
    'api' => 'API prévue',
    'manual' => 'Publication à la main',
    'token_expired' => 'Jeton expiré',
    'disconnect' => 'Déconnecter',

    'connect' => [
        'open' => 'Connecter',
        'title' => 'Connecter :network',
        'help' => 'Indiquez le compte de l’organisation, jamais un compte personnel.',
        'account' => 'Identifiant du compte',
        'account_help' => '3 à 40 caractères, avec ou sans @.',
        'token' => 'Jeton d’accès',
        'token_help' => 'Facultatif tant que le connecteur n’est pas branché. Enregistré chiffré, il ne s’affiche plus ensuite.',
        'submit' => 'Connecter le compte',
    ],

    'confirm' => [
        'disconnect' => 'Déconnecter :network ? Le compte et son jeton sont retirés de la plateforme.',
        'mark' => 'Confirmez-vous avoir publié ce message sur :network ?',
        'cancel' => 'Annuler cette publication planifiée ?',
        'remove' => 'Retirer cette publication de la file ?',
        'delete_template' => 'Supprimer ce modèle de message ?',
    ],

    'schedule' => [
        'title' => 'Planifier une publication',
        'submit' => 'Planifier',
        'connected_only' => '(réseaux connectés seulement)',
        'message_help' => '500 caractères au plus. Obligatoire pour un message personnalisé ; sinon, le titre de la BD ou le texte du modèle est repris.',
        'at_help' => 'À l’heure du Bénin, dans le futur.',
        'fields' => [
            'kind' => 'Contenu',
            'reference' => 'Contenu',
            'reference_bd' => 'Bande dessinée SSR',
            'reference_template' => 'Message clé',
            'message' => 'Message d’accompagnement',
            'channels' => 'Réseaux ciblés',
            'at' => 'Date et heure',
        ],
    ],

    'queue' => [
        'title' => 'File de publication (:count)',
        'empty' => 'Aucune publication planifiée.',
        'custom' => 'Message personnalisé',
        'copy' => 'Copier le texte',
        'copied' => 'Texte copié',
        'mark' => 'Marquer publié',
        'cancel' => 'Annuler',
        'remove' => 'Retirer',
    ],

    'templates' => [
        'title' => 'Modèles de messages clés',
        'new' => 'Nouveau modèle',
        'edit_title' => 'Modifier le modèle',
        'empty' => 'Aucun modèle.',
        'inactive' => 'Inactif',
        'fields' => ['title' => 'Titre', 'body' => 'Texte', 'is_active' => 'Proposé à la planification'],
    ],

    'rooms' => [
        'title' => 'Salons de discussion entre pair·es',
        'new' => 'Nouveau salon',
        'edit_title' => 'Modifier le salon',
        'empty' => 'Aucun salon.',
        'inactive' => 'Inactif',
        'activate' => 'Activer',
        'deactivate' => 'Désactiver',
        'summary' => ':max personnes au plus · :moderation · :anonymous',
        'moderated' => 'modération par mots interdits',
        'free' => 'sans modération automatique',
        'pseudonym' => 'sous pseudonyme',
        'named' => 'sans pseudonyme',
        'max_help' => 'De 5 à 500 personnes.',
        'fields' => [
            'name' => 'Nom', 'description' => 'Description', 'max_members' => 'Taille maximale', 'is_moderated' => 'Modération automatique (mots interdits)',
            'allow_anonymous' => 'Participation sous pseudonyme', 'is_active' => 'Salon ouvert',
        ],
    ],

    'toasts' => [
        'connected' => ':network est connecté.',
        'disconnected' => ':network est déconnecté.',
        'scheduled' => 'Publication planifiée.',
        'marked' => 'Publication marquée comme publiée.',
        'cancelled' => 'Publication annulée.',
        'removed' => 'Publication retirée de la file.',
        'template_saved' => 'Modèle enregistré.',
        'template_deleted' => 'Modèle supprimé.',
        'room_saved' => 'Salon enregistré.',
        'room_activated' => 'Salon ouvert.',
        'room_deactivated' => 'Salon fermé.',
    ],

];
