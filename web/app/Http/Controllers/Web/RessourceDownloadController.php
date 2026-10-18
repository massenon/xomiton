<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Content\ResourceDownload;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * `/ressources/{slug}/telecharger` (P4-03) : compte le téléchargement puis redirige (302) vers un lien signé de courte
 * durée servi par SignedMediaController (`media.signed`). Ressource non publiée, actualité, sans fichier → 404. La
 * redirection n'est jamais mise en cache (la signature expire) ni indexée ; limiteur `resource_download` sur la route.
 */
final class RessourceDownloadController extends Controller
{
    public function __invoke(Request $request, string $slug, ResourceDownload $files): RedirectResponse
    {
        $resource = $files->find($slug);
        abort_if($resource === null, 404);

        $files->record($resource, $request);

        return redirect()->to($files->signedUrl($resource), 302)->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
