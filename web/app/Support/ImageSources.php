<?php

declare(strict_types=1);

namespace App\Support;

use App\Application\Shared\StoredMedia;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Sources `<picture>` d'une image téléversée (P4-12, PERFORMANCE.md §2), pour `<x-img :media>`.
 *
 * S'appuie sur ce que MediaUploader produit réellement : un WebP principal, un repli JPEG / PNG et des variantes
 * `thumb` (320), `medium` (1024), `large` (1600), chacune avec son repli. Aucun AVIF n'est fabriqué : GD n'encode
 * l'AVIF que s'il est compilé avec libavif, ce que l'hébergement mutualisé ne garantit pas ; le jour où l'uploader
 * produira un `image/avif`, il sera placé en première source sans toucher au composant.
 *
 * Les candidats plus larges que `$maxWidth` (1600 px par défaut) sont écartés dès qu'un candidat plus petit existe.
 * Seuls les médias du disque public sont acceptés : un fichier privé passe par un lien signé, jamais par `<img>`.
 */
final class ImageSources
{
    public const MAX_WIDTH = 1600;

    public const DEFAULT_SIZES = '(max-width: 1600px) 100vw, 1600px';

    /** Ordre de préférence des formats dans `<picture>` : du plus compact au repli universel. */
    private const FORMAT_ORDER = ['image/avif', 'image/webp'];

    /**
     * Variante WebP d'une image livrée avec l'application (`public/assets/…`, jpg / jpeg / png) : le fichier frère de même
     * nom en `.webp` s'il existe, et `-320.webp` (320 px) s'il existe aussi (couvertures de 640 px de large). Rien
     * pour une URL externe ou un téléversement (ceux-ci passent par `<x-img :media>`). Une seule vérification de fichier
     * par image et par processus.
     *
     * @return string|null valeur de `srcset` du `<source type="image/webp">`
     */
    public static function bundledWebp(string $url): ?string
    {
        static $memo = [];

        return $memo[$url] ??= (static function () use ($url): ?string {
            $base = rtrim((string) asset(''), '/').'/';

            if (! str_starts_with($url, $base.'assets/') || ! preg_match('#^(assets/.+)\.(?:jpe?g|png)$#i', substr($url, strlen($base)), $m)) {
                return null;
            }

            if (! is_file(public_path($m[1].'.webp'))) {
                return '';
            }

            $main = asset($m[1].'.webp');

            return is_file(public_path($m[1].'-320.webp')) ? asset($m[1].'-320.webp').' 320w, '.$main.' 640w' : $main;
        })() ?: null;
    }

    /**
     * @param  StoredMedia|array<string, mixed>  $media  StoredMedia ou sa forme `toArray()` (colonne JSON d'un modèle)
     * @return array{src: string, srcset: string|null, width: int|null, height: int|null, sources: array<string, string>}
     */
    public static function fromMedia(StoredMedia|array $media, int $maxWidth = self::MAX_WIDTH): array
    {
        $media = $media instanceof StoredMedia ? $media->toArray() : $media;
        $disk = (string) ($media['disk'] ?? '');

        if ($disk !== (string) config('xomiton.uploads.public_disk', 'public')) {
            throw new InvalidArgumentException(sprintf('<x-img> only renders media stored on the public disk, [%s] given.', $disk));
        }

        $candidates = self::candidates($media);
        usort($candidates, static fn (array $a, array $b): int => $a['width'] <=> $b['width']);

        $withinBudget = array_values(array_filter($candidates, static fn (array $c): bool => $c['width'] <= $maxWidth));

        if ($withinBudget !== []) {
            $candidates = $withinBudget;
        }

        $byType = [];

        foreach ($candidates as $candidate) {
            foreach ($candidate['files'] as $mime => $path) {
                $byType[$mime][] = self::url($disk, $path).' '.$candidate['width'].'w';
            }
        }

        // Le repli (`<img>`) est le format le moins moderne disponible ; les autres deviennent des `<source>`.
        $fallbackType = self::fallbackType(array_keys($byType));
        $fallbackCandidates = $byType[$fallbackType];
        unset($byType[$fallbackType]);

        $largest = $candidates[array_key_last($candidates)];
        $sources = [];

        foreach (self::FORMAT_ORDER as $mime) {
            if (isset($byType[$mime])) {
                $sources[$mime] = implode(', ', $byType[$mime]);
            }
        }

        return [
            'src' => self::url($disk, $largest['files'][$fallbackType]),
            'srcset' => count($fallbackCandidates) > 1 ? implode(', ', $fallbackCandidates) : null,
            'width' => $largest['width'],
            'height' => $largest['height'],
            'sources' => $sources,
        ];
    }

    /**
     * Image principale et variantes, chacune avec ses fichiers par type MIME.
     *
     * @param  array<string, mixed>  $media
     * @return list<array{width: int, height: int|null, files: array<string, string>}>
     */
    private static function candidates(array $media): array
    {
        $rows = [$media, ...array_values((array) ($media['variants'] ?? []))];
        $candidates = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! is_string($row['path'] ?? null) || (int) ($row['width'] ?? 0) < 1) {
                continue;
            }

            $files = [self::mimeOf((string) ($row['mime'] ?? ''), $row['path']) => $row['path']];

            if (is_string($row['fallback_path'] ?? null) && $row['fallback_path'] !== '') {
                $files[self::mimeOf('', $row['fallback_path'])] = $row['fallback_path'];
            }

            $candidates[] = ['width' => (int) $row['width'], 'height' => isset($row['height']) ? (int) $row['height'] : null, 'files' => $files];
        }

        if ($candidates === []) {
            throw new InvalidArgumentException('<x-img> media has no displayable image (path and width are required).');
        }

        return $candidates;
    }

    /**
     * @param  list<string>  $types
     */
    private static function fallbackType(array $types): string
    {
        foreach ($types as $type) {
            if (! in_array($type, self::FORMAT_ORDER, true)) {
                return $type;
            }
        }

        // Aucun repli classique (image_keep_fallback = false) : le format le moins moderne présent fait office de repli.
        foreach (array_reverse(self::FORMAT_ORDER) as $type) {
            if (in_array($type, $types, true)) {
                return $type;
            }
        }

        return $types[0];
    }

    private static function mimeOf(string $declared, string $path): string
    {
        if ($declared !== '') {
            return strtolower($declared);
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'avif' => 'image/avif',
            'webp' => 'image/webp',
            'png' => 'image/png',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }

    private static function url(string $disk, string $path): string
    {
        return Storage::disk($disk)->url(ltrim($path, '/'));
    }
}
