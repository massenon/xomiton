<?php

declare(strict_types=1);

/*
 * Synchronisation hors connexion (P9-06, P9-07, SOFTWARE_ARCHITECTURE.md §5, docs/API.md §21) : réponses de
 * `sync/push`, `sync/pull`, `sync/conflicts` et `heartbeat` lues par l'application mobile et l'espace pair. Espace
 * connecté : tutoiement (VOICE_AND_TONE.md §3).
 */
return [

    'device' => [
        'required' => 'Enregistre d’abord cet appareil dans l’application pour synchroniser tes données.',
    ],

    'cursor_invalid' => 'Le point de reprise de la synchronisation est illisible. Relance une synchronisation complète.',

    'items' => [
        'anonymous' => 'Un signalement anonyme part par l’envoi anonyme, jamais par la synchronisation de ton compte.',
        'unknown_type' => 'Ce type d’envoi n’existe pas.',
        'uuid_reused' => 'Cet identifiant d’envoi a déjà servi pour un autre contenu.',
        'not_found' => 'Ce contenu n’existe plus ou ne t’est plus accessible.',
        'forbidden' => 'Tu ne peux pas faire cette action.',
        'retry' => 'Cet envoi n’a pas pu être traité. Il repartira au prochain passage.',
        'too_many' => 'Trop d’envois de ce type en peu de temps. Celui-ci reste dans ta boîte d’envoi et repartira plus tard.',
        'maintenance' => 'Xomiton est en maintenance. Cet envoi reste dans ta boîte d’envoi et repartira à la fin de la maintenance.',
        'conflict' => 'Une modification faite ailleurs croise la tienne. L’équipe va choisir la bonne version.',
        'field_locked' => 'Ce champ ne se modifie pas depuis l’appareil.',
        'base_missing' => 'Indique la valeur que tu avais avant ta modification.',
    ],

    'fields' => [
        'items' => 'éléments',
        'uuid' => 'identifiant de l’envoi',
        'type' => 'type d’envoi',
        'op' => 'opération',
        'payload' => 'contenu',
        'pending' => 'envois en attente',
        'since' => 'point de reprise',
        'collections' => 'collections',
        'collection' => 'collection',
        'conversation' => 'conversation',
        'body' => 'message',
        'changes' => 'modifications',
        'base' => 'valeurs d’origine',
    ],

];
