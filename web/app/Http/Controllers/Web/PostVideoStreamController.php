<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Feed\PostVideoStream;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * `/media/fil/{ulid}/video` (Q96) : source du lecteur vidéo d'une publication. Adresse signée **courte** et propre à la
 * personne qui lit (PostLinks::video) ; signature absente, altérée ou expirée → 403 ; publication invisible pour cette
 * personne, vidéo non relue (hors auteur·rice et modération), supprimée ou fichier absent → **404 neutre**. Le fichier du
 * disque privé est servi par BinaryFileResponse : plages `Range` (206, `Content-Range`) et `Accept-Ranges: bytes`
 * (iOS Safari, avance dans la vidéo sans tout recharger). Jamais mise en cache (`private, no-store`), jamais indexée,
 * type MIME verrouillé, aucune ressource externe permise.
 */
final class PostVideoStreamController extends Controller
{
    public function __invoke(Request $request, string $ulid, PostVideoStream $videos): BinaryFileResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $viewer = $request->query('viewer');
        $video = is_string($viewer) && ctype_digit($viewer) ? $videos->find($ulid, (int) $viewer) : null;

        abort_if($video === null, 404);

        $response = new BinaryFileResponse($video['path'], 200, [
            'Content-Type' => $video['mime'],
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], public: false, autoEtag: false, autoLastModified: true);

        return $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $video['name']);
    }
}
