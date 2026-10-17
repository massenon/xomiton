<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Shared\MediaUploader;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sert un fichier privé (storage/app/private, hors racine web) à partir d'un lien temporaire signé produit par
 * MediaUploader::signedUrl() (P3-04). Signature absente, altérée ou expirée → 403 ; disque hors liste, chemin suspect
 * ou fichier absent → 404. Réponse non mise en cache, jamais indexée, type MIME verrouillé (`nosniff`).
 *
 * Route à déclarer (routes/web.php) :
 *   Route::get('/media/{disk}/{path}', SignedMediaController::class)->where('path', '.*')->name('media.signed');
 */
final class SignedMediaController extends Controller
{
    public function __invoke(Request $request, string $disk, string $path): StreamedResponse
    {
        abort_unless($request->hasValidSignature() || $request->hasValidRelativeSignature(), 403);
        abort_unless(in_array($disk, MediaUploader::signedDisks(), true), 404);

        $path = ltrim(str_replace('\\', '/', $path), '/');

        abort_if($path === '' || str_contains($path, '..') || str_contains($path, "\0"), 404);

        $storage = Storage::disk($disk);

        abort_unless($storage->exists($path), 404);

        return $storage->response($path, basename($path), [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
