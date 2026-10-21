<?php

declare(strict_types=1);

namespace App\Application\Admin\Content;

use App\Application\Shared\Exceptions\MediaRejected;
use App\Application\Shared\MediaUploader;
use App\Domain\Content\Exceptions\ContentRefused;
use App\Enums\ResourceKind;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Téléversements de l'administration du contenu (P12-03, P12-04) par MediaUploader (P3-04) : le type réel est lu dans
 * le contenu, les images sont ré-encodées (métadonnées retirées). Illustrations, images Open Graph et images CCC vont
 * sur le disque public (visuels non sensibles) ; le fichier téléchargeable d'une ressource sur le disque privé, servi
 * par lien signé. Les sous-titres WebVTT (texte, hors des collections de MediaUploader) sont contrôlés ici : extension
 * `.vtt`, première ligne `WEBVTT`, 512 Ko au plus.
 */
final class ContentUploads
{
    public const OG_WIDTH = 1200;

    public const OG_HEIGHT = 630;

    public const SUBTITLES_MAX_BYTES = 524_288;

    public function __construct(private readonly MediaUploader $uploader) {}

    /**
     * Illustration ou couverture : chemin relatif au disque public.
     *
     * @throws ContentRefused
     */
    public function image(UploadedFile $file): string
    {
        return $this->store($file, 'images', true);
    }

    /**
     * Image Open Graph : exactement 1200 × 630 px.
     *
     * @throws ContentRefused
     */
    public function ogImage(UploadedFile $file): string
    {
        $size = @getimagesize((string) $file->getRealPath());

        if ($size === false || $size[0] !== self::OG_WIDTH || $size[1] !== self::OG_HEIGHT) {
            throw new ContentRefused('og_size', ['width' => self::OG_WIDTH, 'height' => self::OG_HEIGHT]);
        }

        return $this->store($file, 'images', true);
    }

    /**
     * Fichier téléchargeable ou lisible d'une ressource (PDF, audio, vidéo) : disque privé. Renvoie [chemin, octets].
     *
     * @return array{0: string, 1: int}
     *
     * @throws ContentRefused
     */
    public function resourceFile(UploadedFile $file, ResourceKind $kind): array
    {
        $collection = match ($kind) {
            ResourceKind::Pdf => 'documents',
            ResourceKind::Audio => 'audio',
            ResourceKind::Video => 'video',
            default => throw new ContentRefused('kind_without_file'),
        };

        try {
            $media = $this->uploader->upload($file, $collection);
        } catch (MediaRejected $rejected) {
            throw new ContentRefused('upload_rejected', ['message' => $rejected->getMessage()]);
        }

        return [$media->path, $media->size];
    }

    /**
     * Sous-titres WebVTT : disque privé.
     *
     * @throws ContentRefused
     */
    public function subtitles(UploadedFile $file): string
    {
        $content = (string) @file_get_contents((string) $file->getRealPath());
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if ($extension !== 'vtt' || $file->getSize() > self::SUBTITLES_MAX_BYTES || ! str_starts_with(ltrim($content, "\xEF\xBB\xBF"), 'WEBVTT')) {
            throw new ContentRefused('subtitles_invalid');
        }

        $path = 'subtitles/'.now()->format('Y/m').'/'.Str::lower((string) Str::ulid()).'.vtt';
        Storage::disk((string) config('xomiton.uploads.private_disk', 'local'))->put($path, $content);

        return $path;
    }

    /** Supprime un fichier devenu inutile (l'ancien après un remplacement) ; les chemins `assets/…` du dépôt ne sont jamais touchés. */
    public function discard(?string $path, bool $public): void
    {
        if ($path === null || $path === '' || str_starts_with($path, 'assets/') || str_starts_with($path, 'http')) {
            return;
        }

        if ($public) {
            $this->uploader->delete($path, (string) config('xomiton.uploads.public_disk', 'public'));

            return;
        }

        Storage::disk((string) config('xomiton.uploads.private_disk', 'local'))->delete($path);
    }

    /** @throws ContentRefused */
    private function store(UploadedFile $file, string $collection, bool $public): string
    {
        try {
            return $this->uploader->upload($file, $collection, $public)->path;
        } catch (MediaRejected $rejected) {
            throw new ContentRefused('upload_rejected', ['message' => $rejected->getMessage()]);
        }
    }
}
