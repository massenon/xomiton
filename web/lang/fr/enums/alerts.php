<?php

declare(strict_types=1);

/*
 * Libellés des enums du domaine « alertes » (lot 6) : app/Enums/AlertType, AlertStatus, AlertPriority,
 * AlertSource, NotificationChannel, DeliveryStatus.
 */
return [

    // Q29 : la valeur stockée reste `denonciation`, le libellé affiché est « Signalement ».
    'alert_type' => [
        'denonciation' => 'Signalement',
        'besoin' => 'Besoin',
        'sos' => 'SOS',
    ],

    'alert_status' => [
        'nouveau' => 'Nouveau',
        'pris_en_charge' => 'Pris en charge',
        'en_cours' => 'En cours',
        'traite' => 'Traité',
        'clos' => 'Clos',
    ],

    'alert_priority' => [
        'normale' => 'Normale',
        'haute' => 'Haute',
        'critique' => 'Critique',
    ],

    'alert_source' => [
        'web' => 'Site web',
        'app' => 'Application mobile',
        'sync-offline' => 'Synchronisation hors connexion',
        'annuaire' => 'Demande de rappel (annuaire)',
    ],

    'notification_channel' => [
        'push' => 'Notification push',
        'sms' => 'SMS',
        'mail' => 'E-mail',
        'database' => 'Cloche de l’administration',
    ],

    'delivery_status' => [
        'pending' => 'En attente',
        'sent' => 'Envoyée',
        'failed' => 'Échec',
    ],

];
