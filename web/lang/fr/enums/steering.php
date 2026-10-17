<?php

declare(strict_types=1);

/*
 * Libellés des enums du domaine « pilotage et modération » (lot 8) : app/Enums/IndicatorFrequency, ReportKind,
 * ReportFormat, JobStatus, ModerationKind, ModerationStatus.
 */
return [

    'indicator_frequency' => [
        'hebdomadaire' => 'Hebdomadaire',
        'mensuelle' => 'Mensuelle',
        'trimestrielle' => 'Trimestrielle',
        'semestrielle' => 'Semestrielle',
        'annuelle' => 'Annuelle',
    ],

    'report_kind' => [
        'mensuel' => 'Rapport mensuel',
        'trimestriel' => 'Rapport trimestriel',
        'semestriel' => 'Rapport semestriel',
        'annuel' => 'Rapport annuel',
        'bailleur' => 'Rapport bailleur',
        'personnalise' => 'Rapport personnalisé',
    ],

    'report_format' => [
        'pdf' => 'PDF',
        'xlsx' => 'Excel',
        'docx' => 'Word',
        'apercu' => 'Aperçu',
    ],

    'job_status' => [
        'pending' => 'En attente',
        'running' => 'En cours',
        'done' => 'Terminé',
        'failed' => 'Échec',
    ],

    'moderation_kind' => [
        'publication' => 'Publication',
        'commentaire' => 'Commentaire',
        'message' => 'Message privé',
        'utilisateur' => 'Utilisateur·rice',
        'sujet' => 'Sujet de forum',
        'reponse' => 'Réponse de forum',
    ],

    'moderation_status' => [
        'en_attente' => 'En attente',
        'traite' => 'Traité',
        'rejete' => 'Rejeté',
    ],

];
