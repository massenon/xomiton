<?php

declare(strict_types=1);

/*
 * Libellés des enums du domaine « communication » (lot 10) : app/Enums/TemplateCategory, MailTransport,
 * SubscriberStatus, SubscriberSource, CampaignStatus.
 */
return [

    'template_category' => [
        'authentification' => 'Authentification',
        'communaute' => 'Communauté',
        'alertes' => 'Alertes',
        'newsletter' => 'Newsletter',
        'autre' => 'Autre',
    ],

    'mail_transport' => [
        'smtp' => 'SMTP',
        'outbox' => 'File d’attente',
        'none' => 'Non envoyé',
    ],

    'subscriber_status' => [
        'en_attente' => 'En attente de confirmation',
        'actif' => 'Actif',
        'desabonne' => 'Désabonné·e',
        'rebond' => 'Rebond (bounce)',
    ],

    'subscriber_source' => [
        'footer' => 'Pied de page',
        'import' => 'Import',
        'admin' => 'Administration',
        'app' => 'Application',
    ],

    'campaign_status' => [
        'brouillon' => 'Brouillon',
        'planifiee' => 'Planifiée',
        'en_cours' => 'Envoi en cours',
        'envoyee' => 'Envoyée',
        'annulee' => 'Annulée',
    ],

];
