<?php

declare(strict_types=1);

/*
 * Libellés des enums du domaine « plateforme » (lot 11) : app/Enums/BackupKind, ConflictStatus.
 */
return [

    'backup_kind' => [
        'manuel' => 'Manuelle',
        'auto' => 'Automatique',
        'avant_restauration' => 'Avant restauration',
    ],

    'conflict_status' => [
        'a_resoudre' => 'À résoudre',
        'resolu' => 'Résolu',
    ],

    // P18-13 : annonce globale.
    'announcement_level' => [
        'info' => 'Information',
        'important' => 'Important',
    ],

    'announcement_audience' => [
        'membres' => 'Tous les membres',
        'pairs' => 'Pair·es seulement',
    ],

];
