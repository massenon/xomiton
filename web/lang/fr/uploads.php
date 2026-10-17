<?php

declare(strict_types=1);

/*
 * Téléversements (P3-04, App\Application\Shared\MediaUploader, App\Rules\UploadAllowed) : motifs de refus et libellés
 * des formats. Formulations impersonnelles : les messages servent dans l'espace membre (tutoiement) comme dans
 * l'administration (vouvoiement). Ils disent comment corriger (MICROCOPY.md).
 */
return [

    'rejected' => [
        'unreadable' => 'Le fichier n’a pas pu être lu. Choisir un autre fichier et réessayer.',
        'type' => 'Ce type de fichier n’est pas accepté ici. Formats acceptés : :types.',
        'too_large' => 'Le fichier est trop lourd (:size). Poids maximal : :max.',
        'corrupt_image' => 'Cette image est illisible ou abîmée. Choisir une autre image.',
        'too_many_pixels' => 'Cette image est trop grande (:width × :height px). Réduire ses dimensions avant de la téléverser.',
        'not_a_file' => 'Aucun fichier n’a été reçu.',
    ],

    // Libellés courts des types MIME acceptés (liste « Formats acceptés »).
    'types' => [
        'image/jpeg' => 'JPEG',
        'image/png' => 'PNG',
        'image/webp' => 'WebP',
        'application/pdf' => 'PDF',
        'audio/mpeg' => 'MP3',
        'audio/mp4' => 'M4A',
        'audio/x-m4a' => 'M4A',
        'audio/ogg' => 'OGG',
        'video/mp4' => 'MP4',
        'video/webm' => 'WebM',
    ],

    'collections' => [
        'images' => 'Images',
        'documents' => 'Documents',
        'audio' => 'Sons',
        'video' => 'Vidéos',
    ],

    // Avertissement affiché près des champs de preuve (P5-04) : les métadonnées sont retirées côté serveur.
    'metadata_notice' => 'Les métadonnées (lieu, appareil, date) sont retirées des images au téléversement.',

    'units' => [
        'kb' => ':value Ko',
        'mb' => ':value Mo',
    ],
];
