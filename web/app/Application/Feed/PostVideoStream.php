<?php

declare(strict_types=1);

namespace App\Application\Feed;

use App\Application\Shared\MediaUploader;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Fichier vidéo d'une publication prêt à être diffusé (Q96, GET /media/fil/{ulid}/video) : la personne désignée par le
 * lien signé (`viewer`) doit toujours exister, être active et **pouvoir voir la publication** (PostPolicy::view : audience,
 * groupe, blocages ; une vidéo en relecture ou refusée n'est lisible que par son auteur·rice et la modération). Publication
 * supprimée, vidéo sans fichier (supprimé après refus ou retrait), fichier absent ou qui n'est pas du mp4 / webm (type réel,
 * libmagic) → null, que le contrôleur traduit en 404 neutre : rien ne distingue « n'existe pas » de « tu ne peux pas la voir ».
 */
final class PostVideoStream
{
    /**
     * @return array{path: string, mime: string, name: string}|null
     */
    public function find(string $ulid, int $viewerId): ?array
    {
        $viewer = User::query()->find($viewerId);

        if (! $viewer instanceof User || ! $viewer->isActive()) {
            return null;
        }

        $post = Post::query()->where('ulid', $ulid)->first();

        if ($post === null || ! $viewer->can('view', $post)) {
            return null;
        }

        $media = PostMedia::query()->where('post_id', $post->getKey())->where('kind', PostMedia::KIND_VIDEO)->first();
        $relative = $media === null ? '' : ltrim(str_replace('\\', '/', (string) $media->path), '/');
        $storage = Storage::disk(MediaUploader::privateDisk());

        if ($relative === '' || str_contains($relative, '..') || ! $storage->exists($relative)) {
            return null;
        }

        $path = $storage->path($relative);
        $mime = MediaUploader::detectMime($path);

        if (! in_array($mime, FeedMediaLimits::videoMimes(), true)) {
            return null;
        }

        return ['path' => $path, 'mime' => $mime, 'name' => $post->ulid.'.'.($mime === 'video/webm' ? 'webm' : 'mp4')];
    }
}
