<?php

declare(strict_types=1);

// Libellés des enums du lot « référentiels » (app/Enums : AgeRange, Sex, DirectoryRole).
return [
    'age_range' => [
        '10-14' => '10-14 ans',
        '15-17' => '15-17 ans',
        '18-24' => '18-24 ans',
        '25-34' => '25-34 ans',
        '35-49' => '35-49 ans',
        '50+' => '50 ans et plus',
    ],
    'sex' => [
        'femme' => 'Femme',
        'homme' => 'Homme',
        'autre' => 'Autre / non-binaire',
    ],
    'directory_role' => [
        'point_focal' => 'Point focal',
        'juriste' => 'Juriste',
        'psychologue' => 'Psychologue',
        'gynecologue' => 'Gynécologue',
        'responsable_reseau' => 'Responsable de réseau',
        'pair_animateur' => 'Pair animateur·rice',
        'travailleur_social' => 'Travailleur·se social·e',
        'personnel_identifie' => 'Personnel identifié',
    ],
];
