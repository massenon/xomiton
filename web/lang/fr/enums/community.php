<?php

declare(strict_types=1);

/*
 * Libellés des enums du domaine « communauté » (lot 5) : app/Enums/RsvpStatus, ConversationKind.
 */
return [

    'rsvp_status' => [
        'going' => 'Participe',
        'interested' => 'Intéressé·e',
        'declined' => 'Ne participe pas',
    ],

    'conversation_kind' => [
        'private' => 'Conversation privée',
        'group' => 'Conversation de groupe',
    ],

    // P18-14 : type d'un événement, boîte à suggestions.
    'event_type' => [
        'groupe_parole' => 'Groupe de parole',
        'atelier' => 'Atelier',
        'rencontre' => 'Rencontre',
        'autre' => 'Autre',
    ],

    'suggestion_category' => [
        'fonctionnalite' => 'Fonctionnalité',
        'contenu' => 'Contenu',
        'securite' => 'Sécurité',
        'autre' => 'Autre',
    ],

    'suggestion_status' => [
        'nouvelle' => 'Nouvelle',
        'en_cours' => 'En cours',
        'retenue' => 'Retenue',
        'ecartee' => 'Écartée',
    ],

];
