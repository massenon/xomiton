<?php

declare(strict_types=1);

/*
 * Mode maintenance applicatif (RG-14, P2-11 ; page 503 sobre, gabarit plain).
 * Registre (VOICE_AND_TONE.md §3, Q36) : page rendue par la coquille publique → vous ; la clé `disconnected`
 * s'adresse à un·e membre dont la session vient d'être fermée → tu (même choix que errors.419.member).
 */
return [

    'page_title' => 'Maintenance en cours',
    'title' => 'Maintenance en cours',
    'default_message' => 'Xomiton est en maintenance. Nous revenons très vite.',
    'since' => 'Maintenance commencée le :date à :time.',
    'until' => 'Reprise prévue le :date à :time.',
    'until_unknown' => 'Nous n’avons pas encore d’heure de reprise à annoncer. Réessayez dans quelques minutes.',
    'disconnected' => 'Pour protéger ton compte, ta session a été fermée. Tes brouillons restent sur ton appareil : reconnecte-toi dès la fin de la maintenance.',

    'always_available' => [
        'title' => 'En danger ou besoin d’aide maintenant ?',
        'text' => 'Le SOS, le signalement et les contacts d’urgence restent disponibles pendant la maintenance, avec ou sans compte.',
        'sos' => 'SOS',
        'report' => 'Signaler',
        'emergency' => 'Contacts d’urgence',
        'numbers_title' => 'Numéros d’urgence',
    ],

    'refresh' => 'Actualiser la page',
    'admin_login' => 'Connexion de l’administration',
    'lock_screen_notice' => 'Aucune donnée n’est visible depuis l’écran de verrouillage.',

];
