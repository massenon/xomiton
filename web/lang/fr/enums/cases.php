<?php

declare(strict_types=1);

/*
 * Libellés des enums du domaine « dossiers » (lot 7) : app/Enums/CaseKind, CaseStatus, FollowupOutcome,
 * et des services des fiches de référence (App\Models\CaseForm::SERVICES, admin/fiches.php du prototype).
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

    // Libellés de admin/fiches.php ($servicesList) ; `autre` ajouté par le dictionnaire.
    'services' => [
        'ecoute' => 'Écoute / accueil',
        'medical' => 'Prise en charge médicale',
        'psy' => 'Soutien psychologique',
        'juridique' => 'Appui juridique',
        'hebergement' => 'Hébergement d’urgence',
        'dignite' => 'Kit de dignité / matériel',
        'securite' => 'Mise en sécurité',
        'autre' => 'Autre',
    ],

];
