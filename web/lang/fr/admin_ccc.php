<?php

declare(strict_types=1);

/*
 * Administration, Outils CCC `/admin/ccc` (P12-04 ; prototype admin/ccc.php). Registre : vous, neutre et précis
 * (VOICE_AND_TONE.md §3, administration).
 */
return [

    'title' => 'Outils CCC',
    'intro' => 'Manuel du pair animateur, boîtes à images, bandes dessinées SSR et textes de loi. Un outil masqué disparaît des écrans des membres et des pair·es ; vous pouvez remplacer son image et décrire chaque planche pour les personnes qui ne la voient pas.',
    'tabs_label' => 'Types d’outils',
    'tabs' => ['manuel' => 'Manuel', 'boite_images' => 'Boîtes à images', 'bd' => 'BD SSR', 'lois' => 'Lois'],
    'kpi' => ['total' => 'Outils numérisés', 'published' => 'Publiés', 'hidden' => 'Masqués', 'images' => 'Images remplacées'],
    'published' => 'Publié',
    'hidden' => 'Masqué',
    'empty' => 'Aucun outil dans cette section.',
    'empty_laws' => 'Aucun texte de loi.',
    'laws_title' => 'Lois et codes du Bénin',
    'chapters' => '{0} Aucun chapitre|{1} 1 chapitre|[2,*] :count chapitres',
    'pages' => '{0} Aucune planche|{1} 1 planche|[2,*] :count planches',
    'minutes' => ':count min',

    'image' => [
        'open' => 'Image',
        'title' => 'Remplacer l’image : :title',
        'label' => 'Nouvelle image',
        'help' => 'JPG, PNG ou WebP, 5 Mo au plus. Elle remplace la vignette de l’outil (et la planche d’une boîte à images).',
        'save' => 'Téléverser',
    ],

    'alts' => [
        'open' => 'Planches',
        'title' => 'Textes alternatifs : :title',
        'help' => 'Décrivez chaque planche en une phrase pour les personnes qui ne la voient pas (:max caractères au plus).',
        'page' => 'Planche :number',
        'save' => 'Enregistrer',
    ],

    'toasts' => [
        'published' => '« :title » est publié.',
        'unpublished' => '« :title » est masqué.',
        'image' => 'Image de « :title » remplacée.',
        'alts' => 'Textes alternatifs de « :title » enregistrés.',
    ],

];
