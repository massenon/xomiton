<?php

declare(strict_types=1);

namespace App\Application\Feed;

use App\Application\Shared\MediaUploader;
use App\Models\Post;
use App\Models\PostMedia;
use App\Support\MediaUrl;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * Adresses d'une publication (ADR-27 : toujours par ULID, jamais par identifiant) : détail dans l'espace membre
 * (`/fil/{ulid}`, P7-06), lien de partage neutre (`/p/{ulid}`, Q31 b) et URL de ses médias.
 *
 * Médias : les images téléversées par l'API vivent sur le disque **privé** (une publication « abonné·es » ou « moi » ne
 * doit pas être lisible par quiconque devine l'adresse) et sortent en lien signé (`media.signed`,
 * `xomiton.feed.media_url_minutes`) dont l'expiration est alignée sur une fenêtre fixe (MediaUploader::stableExpiry,
 * `media_url_window_minutes`) : le corps d'une page du fil ne change pas d'une lecture à l'autre dans la fenêtre, l'ETag
 * non plus (304) ; les visuels importés du prototype (`assets/…`, disque public) gardent leur URL.
 */
final class PostLinks
{
    public function __construct(private readonly MediaUploader $uploader) {}

    public static function show(Post $post): string
    {
        return Route::has('fil.show') ? route('fil.show', ['post' => $post->ulid]) : url('/fil/'.$post->ulid);
    }

    public static function share(Post $post): string
    {
        return Route::has('p.show') ? route('p.show', ['ulid' => $post->ulid]) : url('/p/'.$post->ulid);
    }

    public function media(PostMedia $media): ?string
    {
        $path = ltrim((string) $media->path, '/');

        if ($path === '') {
            return null;
        }

        if (preg_match('#^https?://#', $path) === 1 || str_starts_with($path, 'assets/')) {
            return MediaUrl::exists($path) ? MediaUrl::resolve($path, $path) : null;
        }

        $private = MediaUploader::privateDisk();

        if (Storage::disk($private)->exists($path)) {
            return $this->uploader->signedUrl($private, $path, MediaUploader::stableExpiry(
                (int) config('xomiton.feed.media_url_minutes', 60),
                (int) config('xomiton.feed.media_url_window_minutes', 15),
            ));
        }

        return MediaUrl::exists($path) ? MediaUrl::resolve($path, $path) : null;
    }
}
