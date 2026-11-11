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

    'comic' => [
        'new' => 'Nouvelle BD',
        'create_title' => 'Nouvelle bande dessinée',
        'edit_open' => 'Titre',
        'edit_title' => 'Modifier le titre : :title',
        'title_label' => 'Titre',
        'title_help' => ':max caractères au plus. La clé publique (bd1, bd2…) est générée automatiquement et ne change plus.',
        'cover_label' => 'Image de couverture (facultatif)',
        'cover_help' => 'JPG, PNG ou WebP, 5 Mo au plus.',
        'published_label' => 'Publier la BD tout de suite',
        'published_help' => 'Une BD ne se publie qu’avec au moins une planche. Sinon, elle reste masquée.',
        'draft_title' => 'Planches de départ (facultatif)',
        'draft_help' => 'Vous pourrez ajouter, retirer et réordonner les planches plus tard.',
        'draft_add' => 'Ajouter une planche',
        'draft_remove' => 'Retirer cette planche',
        'draft_legend' => 'Planche :number',
        'create' => 'Créer la BD',
        'save' => 'Enregistrer',
        'key' => 'Clé publique : :key',
    ],

    'planches' => [
        'open' => 'Planches',
        'title' => 'Planches : :title',
        'intro' => 'Les planches s’affichent dans l’ordre de cette liste. Le texte alternatif décrit la planche pour les personnes qui ne la voient pas (:max caractères au plus, obligatoire).',
        'list_label' => 'Planches de la BD',
        'legend' => 'Planche :number sur :total',
        'image' => 'Image de la planche',
        'image_help' => 'JPG, PNG ou WebP, 5 Mo au plus.',
        'caption' => 'Légende (facultatif)',
        'alt' => 'Texte alternatif',
        'alt_help' => 'Décrivez la planche en une phrase pour les personnes qui ne la voient pas (:max caractères au plus).',
        'up' => 'Monter',
        'down' => 'Descendre',
        'remove' => 'Retirer',
        'remove_confirm' => 'Retirer la planche :number ? Son image sera supprimée.',
        'remove_yes' => 'Oui, retirer',
        'remove_no' => 'Garder',
        'empty' => 'Cette BD n’a pas encore de planche.',
        'save' => 'Enregistrer les légendes et les textes',
        'add_title' => 'Ajouter une planche',
        'add' => 'Ajouter la planche',
        'moved' => 'Planche déplacée en position :position sur :total.',
    ],

    'delete' => [
        'open' => 'Supprimer',
        'title' => 'Supprimer la BD « :title » ?',
        'text' => 'La BD, sa couverture et ses planches seront supprimées définitivement. Cette action est irréversible.',
    ],

    'toasts' => [
        'published' => '« :title » est publié.',
        'unpublished' => '« :title » est masqué.',
        'image' => 'Image de « :title » remplacée.',
        'comic_created' => 'BD « :title » créée.',
        'comic_renamed' => 'Titre de la BD « :title » enregistré.',
        'comic_deleted' => 'BD « :title » supprimée.',
        'page_added' => 'Planche ajoutée à « :title ».',
        'pages_saved' => 'Planches de « :title » enregistrées.',
        'page_removed' => 'Planche retirée de « :title ».',
    ],

];
