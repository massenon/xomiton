<?php

declare(strict_types=1);

namespace App\Application\Account\Profile;

use App\Application\Feed\PostLinks;
use App\Application\Feed\Queries\UserPostsQuery;
use App\Application\Shared\MediaUploader;
use App\Models\Album;
use App\Models\AlbumMedia;
use App\Models\PostMedia;
use App\Models\User;
use App\Support\MediaUrl;
use Illuminate\Support\Facades\Storage;

/**
 * Onglet « Médias » et carte « Médias » de la colonne du profil (P8-07, prototype `my-profile-media.php`,
 * `x_profile_side()`) : images des publications de la personne que `$viewer` peut lire (UserPostsQuery : visibilité du
 * fil, jamais une publication anonyme, RG-02) et images de ses albums visibles (Album::scopeVisibleTo, médiathèque
 * P8-12), plus récentes d'abord. Images privées en lien signé à fenêtre fixe (PostLinks, MediaUploader::stableExpiry) ;
 * jamais un fichier absent.
 */
final class ProfileMedia
{
    public function __construct(
        private readonly UserPostsQuery $posts,
        private readonly PostLinks $links,
        private readonly MediaUploader $uploader,
    ) {}

    /**
     * @return list<array{id: string, url: string, alt: string|null, width: int|null, height: int|null, href: string|null, at: string|null}>
     */
    public function for(User $viewer, User $owner, int $limit): array
    {
        $postIds = $this->posts->posts($viewer, $owner)->reorder()->select('posts.id');

        $fromPosts = PostMedia::query()->images()->whereIn('post_id', $postIds)
            ->with('post:id,ulid,created_at')
            // toBase() : une collection Eloquent vide resterait « Eloquent » après map() et merge() appellerait getKey()
            // sur les tableaux des albums (500 quand le profil n'a que des images d'album).
            ->latest('id')->limit($limit)->get()->toBase()
            ->map(fn (PostMedia $media): ?array => ($url = $this->links->media($media)) === null ? null : [
                'id' => 'post-'.$media->getKey(),
                'url' => $url,
                'alt' => $media->alt,
                'width' => $media->width,
                'height' => $media->height,
                'href' => $media->post !== null ? PostLinks::show($media->post) : null,
                'at' => $media->post?->created_at?->toIso8601String(),
            ]);

        $albumIds = Album::query()->select('id')->where('user_id', $owner->getKey())->visibleTo($viewer);

        $fromAlbums = AlbumMedia::query()->images()->whereIn('album_id', $albumIds)
            ->latest('id')->limit($limit)->get()->toBase()
            ->map(fn (AlbumMedia $media): ?array => ($url = $this->url((string) $media->path)) === null ? null : [
                'id' => 'album-'.$media->getKey(),
                'url' => $url,
                'alt' => $media->alt,
                'width' => $media->width,
                'height' => $media->height,
                'href' => null,
                'at' => $media->created_at?->toIso8601String(),
            ]);

        return $fromPosts->merge($fromAlbums)->filter()
            ->sortByDesc(static fn (array $media): string => (string) $media['at'])
            ->take($limit)->values()->all();
    }

    /**
     * URL d'une image d'album : visuel importé (`assets/…`) ou URL absolue telle quelle, fichier privé en lien signé,
     * sinon disque public ; null si le fichier manque.
     */
    private function url(string $path): ?string
    {
        $path = ltrim($path, '/');

        if ($path === '') {
            return null;
        }

        $private = MediaUploader::privateDisk();

        if (! str_starts_with($path, 'assets/') && preg_match('#^https?://#', $path) !== 1 && Storage::disk($private)->exists($path)) {
            return $this->uploader->signedUrl($private, $path, MediaUploader::stableExpiry(
                (int) config('xomiton.feed.media_url_minutes', 60),
                (int) config('xomiton.feed.media_url_window_minutes', 15),
            ));
        }

        return MediaUrl::exists($path) ? MediaUrl::resolve($path, $path) : null;
    }
}
