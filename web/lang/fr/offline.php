<?php

declare(strict_types=1);

/*
 * Hors connexion web (P5-10, ADR-26) : boîte d'envoi commune (resources/js/shared/outbox.js, avis de
 * resources/js/public/outbox-notices.js) et page minimale du service worker. Ces messages peuvent s'afficher sur
 * n'importe quelle page (site institutionnel en vous, parcours de crise en tu) : registre neutre (VOICE_AND_TONE.md §3,
 * règle 1), sans numéro en dur : la page minimale du service worker liste les numéros d'urgence du réglage
 * `emergency_numbers` (KnowledgeBaseAnswerer::emergencyNumbers()), injectés dans le script par ServiceWorkerScript.
 */
return [

    'sw' => [
        'title' => 'Hors connexion',
        'text' => 'Cette page n’est pas encore enregistrée sur cet appareil. En danger, appeler directement un numéro d’urgence.',
        'numbers' => 'Numéros d’urgence',
    ],

    'outbox' => [
        'queued' => 'Envoi enregistré sur cet appareil : il partira automatiquement au retour du réseau.',
        'report_sent_title' => 'Signalement envoyé',
        'report_sent' => 'Le signalement enregistré hors connexion est bien parti. Code de suivi :',
        'report_keep' => 'À conserver : ce code ne sera plus affiché.',
        'report_failed_title' => 'Signalement non envoyé',
        'report_failed' => 'Le serveur a refusé le signalement enregistré hors connexion. Les réponses restent sur la page du formulaire.',
        'report_resume' => 'Reprendre le signalement',
        'sos_sent' => 'Alerte SOS envoyée.',
        'sos_failed_title' => 'Alerte SOS non envoyée',
        'sos_failed' => 'L’alerte enregistrée hors connexion n’a pas pu partir. En danger, appeler directement un numéro d’urgence.',
        'sos_contacts' => 'Contacts d’urgence',
        // Publication du fil mise en file hors connexion (P7-04, type `post`), hors de la page /fil qui l'annonce elle-même.
        'post_sent' => 'Publication enregistrée hors connexion : elle est en ligne.',
        'post_failed_title' => 'Publication non envoyée',
        'post_failed' => 'Le serveur a refusé la publication enregistrée hors connexion. Son contenu reste sur cet appareil, prêt à être corrigé.',
        'post_resume' => 'Reprendre la publication',
        'copy' => 'Copier le code',
        'copied' => 'Code copié.',
        'print' => 'Imprimer',
        'close' => 'Fermer',
    ],

];
