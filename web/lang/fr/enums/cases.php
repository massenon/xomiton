<?php

declare(strict_types=1);

/*
 * Libellés des enums du domaine « dossiers » (lot 7) : app/Enums/CaseKind, CaseStatus, FollowupOutcome.
 */
return [

    'case_kind' => [
        'VBG' => 'Violence basée sur le genre',
        'VDH' => 'Violation des droits humains',
    ],

    'case_status' => [
        'ouvert' => 'Ouvert',
        'en_cours' => 'En cours',
        'clos' => 'Clos',
        'archive' => 'Archivé',
    ],

    'followup_outcome' => [
        'planifie' => 'Planifié',
        'realise' => 'Réalisé',
        'reporte' => 'Reporté',
        'annule' => 'Annulé',
    ],

];
