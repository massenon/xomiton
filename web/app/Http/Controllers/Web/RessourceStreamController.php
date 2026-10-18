<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Content\ResourceDownload;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * `/ressources/{slug}/lire` (P4-03) : source du lecteur vidéo / audio de la fiche. Sert le fichier du disque privé
 * sans redirection ni signature (contenu publié, public), plages `Range` comprises : BinaryFileResponse répond 206 avec
 * `Content-Range` et annonce `Accept-Ranges: bytes` (iOS Safari, avance dans le média sans tout recharger en 2G / 3G).
 * Compté une fois par lecture (ResourceDownload::record). Ressource non publiée, non lisible, fichier absent ou qui
 * n'est pas un média → 404. Jamais mise en cache HTTP ni indexée, type MIME verrouillé ; limiteur `resource_download`.
 */
final class RessourceStreamController extends Controller
{
    public function __invoke(Request $request, string $slug, ResourceDownload $files): BinaryFileResponse
    {
        $resource = $files->find($slug);
        $media = $resource === null ? null : $files->media($resource);
        abort_if($resource === null || $media === null, 404);

        $files->record($resource, $request);

        $response = new BinaryFileResponse($media['path'], 200, [
            'Content-Type' => $media['mime'],
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], public: false, autoEtag: false, autoLastModified: true);

        return $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $media['name']);
    }
}
