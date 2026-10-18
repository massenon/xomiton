<?php

declare(strict_types=1);

/*
 * Libellés des enums du domaine « alertes » (lot 6) : app/Enums/AlertType, AlertStatus, AlertPriority,
 * AlertSource, NotificationChannel, DeliveryStatus, RoutingFallback, ContactChannel ; motifs d'alert_escalations.
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

    // RG-04 : niveau de routage retenu (`alerts.routing_fallback`), `manual` = choisi par une personne.
    'routing_fallback' => [
        'role_zone' => 'Rôle demandé dans le département',
        'focal_zone' => 'Point focal du département',
        'focal_national' => 'Point focal national',
        'first_active' => 'Premier contact actif (repli)',
        'manual' => 'Attribution manuelle',
    ],

    'contact_channel' => [
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
        'call' => 'Appel',
        'email' => 'E-mail',
    ],

    // Motifs d'`alert_escalations.reason` (RG-05, Q27) : libellés symboliques, le délai réel est un réglage.
    'escalation_reason' => [
        'no_ack_5min' => 'Sans accusé après 5 min',
        'no_ack_48h' => 'Sans accusé après 48 h',
        'manual' => 'Réattribution manuelle',
    ],

    'delivery_status' => [
        'pending' => 'En attente',
        'sent' => 'Envoyée',
        'failed' => 'Échec',
    ],

];
