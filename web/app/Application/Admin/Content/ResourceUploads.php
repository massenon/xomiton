<?php

declare(strict_types=1);

namespace App\Application\Admin\Content;

use Illuminate\Http\UploadedFile;

/**
 * Fichiers joints au formulaire d'une ressource (P12-03) : illustration, image de partage Open Graph, fichier
 * téléchargeable ou lisible, sous-titres WebVTT. `null` : on garde l'existant.
 */
final readonly class ResourceUploads
{
    public function __construct(
        public ?UploadedFile $image = null,
        public ?UploadedFile $og = null,
        public ?UploadedFile $file = null,
        public ?UploadedFile $subtitles = null,
    ) {}
}
