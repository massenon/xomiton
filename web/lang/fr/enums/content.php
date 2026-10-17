<?php

declare(strict_types=1);

/*
 * Libellés des enums du domaine « contenus » (lot 3) : app/Enums/ContentStatus, ResourceKind,
 * CccKind, BadWordSeverity.
 */
return [

    'content_status' => [
        'brouillon' => 'Brouillon',
        'publie' => 'Publié',
        'masque' => 'Masqué',
    ],

    'resource_kind' => [
        'fiche' => 'Fiche pratique',
        'guide' => 'Guide',
        'video' => 'Vidéo',
        'audio' => 'Audio',
        'pdf' => 'Document PDF',
        'article' => 'Article',
        'lien' => 'Lien externe',
    ],

    'ccc_kind' => [
        'manuel' => 'Module du manuel',
        'boite_images' => 'Boîte à images',
        'bd' => 'Bande dessinée',
        'loi' => 'Fiche de loi',
    ],

    'bad_word_severity' => [
        'bloquer' => 'Bloquer la publication',
        'signaler' => 'Signaler pour relecture',
    ],

];
