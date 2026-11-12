<?php

declare(strict_types=1);

/*
 * Administration, Calendrier des événements `/admin/evenements` (P18-14). Registre : vous (VOICE_AND_TONE.md §3).
 */
return [

    'title' => 'Événements',
    'intro' => 'Tous les événements du mois, semaine par semaine (heure du Bénin), avec leur type et leur état. Masquer ou supprimer un événement se fait dans Réseau social, onglet Événements.',
    'approval' => [
        'title' => 'File d’approbation',
        'on' => 'Activée : un événement créé par un·e membre reste en attente, invisible, jusqu’à votre décision. La modération publie directement.',
        'off' => 'Désactivée : un événement créé par un·e membre est publié aussitôt. La modération peut le masquer ensuite.',
        'switch' => 'Approuver avant publication',
    ],
    'pending' => [
        'title' => '{1} :count événement en attente|[2,*] :count événements en attente',
    ],
    'by' => 'par :name',
    'nav_label' => 'Changer de mois',
    'previous' => 'Mois précédent',
    'next' => 'Mois suivant',
    'current' => 'Ce mois-ci',
    'week' => 'Semaine du :start au :end',
    'week_empty' => 'Aucun événement cette semaine.',
    'empty' => 'Aucun événement ce mois-ci',
    'online' => 'En ligne',
    'going' => '{0} aucun·e participant·e|{1} :count participant·e|[2,*] :count participant·es',
    'refuse_title' => 'Refuser l’événement',
    'reason_help' => 'Obligatoire : ce motif est envoyé à l’organisateur·rice. L’événement reste masqué.',
    'fields' => ['reason' => 'Motif du refus'],
    'actions' => ['view' => 'Voir', 'approve' => 'Approuver', 'refuse' => 'Refuser'],
    'toasts' => [
        'approved' => '« :title » est publié.',
        'refused' => 'Événement refusé, motif envoyé.',
        'approval_on' => 'File d’approbation activée.',
        'approval_off' => 'File d’approbation désactivée.',
    ],
];
