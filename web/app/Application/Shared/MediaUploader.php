<?php

declare(strict_types=1);

namespace App\Application\Shared;

use App\Application\Shared\Exceptions\MediaRejected;
use App\Http\Controllers\Web\SignedMediaController;
use GdImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * Téléversements (P3-04, MASTER_PROMPT.md §4 Sécurité, DEVELOPMENT_STANDARDS.md §7, PERFORMANCE.md §2).
 *
 *   $media = $uploader->upload($request->file('preuve'), 'images');            // disque privé (storage/app/private)
 *   $avatar = $uploader->upload($request->file('avatar'), 'images', public: true); // disque public : visuels non sensibles
 *   $url = $uploader->signedUrl($media->disk, $media->path);                   // lien signé 10 min (route media.signed)
 *
 * 1. le type réel est lu dans le contenu (finfo), l'extension déclarée n'est jamais crue ; collection inconnue, type hors
 *    liste blanche ou poids dépassé → MediaRejected (message traduit, lang/fr/uploads.php) ;
 * 2. les images sont décodées puis ré-encodées par GD : l'orientation EXIF est appliquée, toutes les métadonnées
 *    (EXIF, ICC, XMP) disparaissent, le côté long est ramené à `image_max_dimension`, la sortie est WebP avec un repli
 *    JPEG (source opaque) ou PNG (transparence conservée) et des variantes `thumb` / `medium` ;
 * 3. le nom de fichier est un ULID + extension déduite du type réel ; le nom d'origine n'est gardé que dans le DTO.
 */
final class MediaUploader
{
    public const ROUTE_SIGNED = 'media.signed';

    /** Extension sûre par type réel. */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/ogg' => 'ogg',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
    ];

    /** Formats d'image que GD sait décoder ici. */
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * @throws MediaRejected si le fichier est refusé (type, poids, image illisible)
     * @throws InvalidArgumentException si la collection n'existe pas dans config/xomiton.php
     */
    public function upload(UploadedFile $file, string $collection, bool $public = false): StoredMedia
    {
        $mime = $this->inspect($file, $collection);
        $disk = $public ? self::publicDisk() : self::privateDisk();
        $directory = $collection.'/'.now()->format('Y/m');
        $ulid = strtolower((string) Str::ulid());
        $originalName = self::displayName($file);

        if (in_array($mime, self::IMAGE_MIMES, true) && (bool) config('xomiton.uploads.reencode_images', true)) {
            return $this->storeImage($file, $mime, $disk, $directory, $ulid, $originalName);
        }

        $filename = $ulid.'.'.self::EXTENSIONS[$mime];

        if (Storage::disk($disk)->putFileAs($directory, $file, $filename) === false) {
            throw new RuntimeException(sprintf('Unable to store upload on disk [%s] at [%s/%s].', $disk, $directory, $filename));
        }

        [$width, $height] = in_array($mime, self::IMAGE_MIMES, true) ? self::dimensions($file->getRealPath()) : [null, null];

        return new StoredMedia($disk, $directory.'/'.$filename, $mime, (int) $file->getSize(), $width, $height, $originalName);
    }

    /**
     * Contrôle sans rien écrire (utilisé par App\Rules\UploadAllowed) et renvoie le type MIME réel.
     *
     * @throws MediaRejected
     */
    public function inspect(UploadedFile $file, string $collection): string
    {
        $allowed = self::allowedMimes($collection);
        $maxKb = self::maxKb($collection);

        if (! $file->isValid()) {
            throw in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? MediaRejected::tooLarge((int) $file->getSize(), $maxKb)
                : MediaRejected::unreadable();
        }

        $size = (int) $file->getSize();

        if ($size <= 0) {
            throw MediaRejected::unreadable();
        }

        if ($size > $maxKb * 1024) {
            throw MediaRejected::tooLarge($size, $maxKb);
        }

        $path = (string) $file->getRealPath();
        $mime = self::detectMime($path);

        if (! in_array($mime, $allowed, true)) {
            throw MediaRejected::type($mime, $allowed);
        }

        if (in_array($mime, self::IMAGE_MIMES, true)) {
            $info = @getimagesize($path);

            if ($info === false || ($info['mime'] ?? null) !== $mime || $info[0] < 1 || $info[1] < 1) {
                throw MediaRejected::corruptImage();
            }

            if ((int) config('xomiton.uploads.image_max_pixels', 25_000_000) < $info[0] * $info[1]) {
                throw MediaRejected::tooManyPixels((int) $info[0], (int) $info[1]);
            }
        }

        return $mime;
    }

    /**
     * Supprime un média et tout ce qui en dérive (repli, variantes). Avec un chemin seul, les fichiers frères qui
     * partagent le même ULID dans le dossier sont retirés aussi.
     */
    public function delete(StoredMedia|string $media, ?string $disk = null): void
    {
        if ($media instanceof StoredMedia) {
            Storage::disk($media->disk)->delete($media->allPaths());

            return;
        }

        $disk ??= self::privateDisk();
        $storage = Storage::disk($disk);
        $path = ltrim(str_replace('\\', '/', $media), '/');
        $directory = str_contains($path, '/') ? dirname($path) : '';
        $stem = explode('-', pathinfo($path, PATHINFO_FILENAME), 2)[0];

        $siblings = array_values(array_filter(
            $storage->files($directory),
            static fn (string $candidate): bool => str_starts_with(basename($candidate), $stem.'.') || str_starts_with(basename($candidate), $stem.'-'),
        ));

        $storage->delete(array_values(array_unique([$path, ...$siblings])));
    }

    /**
     * Lien temporaire signé vers un fichier privé, servi par SignedMediaController (route `media.signed`).
     *
     * @throws LogicException si la route n'est pas encore déclarée dans routes/web.php
     */
    public function signedUrl(string $disk, string $path, int $minutes = 10): string
    {
        if (! in_array($disk, self::signedDisks(), true)) {
            throw new InvalidArgumentException(sprintf('Disk [%s] cannot be served through signed URLs (xomiton.uploads.signed_disks).', $disk));
        }

        if (! Route::has(self::ROUTE_SIGNED)) {
            throw new LogicException(sprintf(
                "Route [%s] is not registered. Add to routes/web.php: Route::get('/media/{disk}/{path}', %s::class)->where('path', '.*')->name('%s');",
                self::ROUTE_SIGNED,
                SignedMediaController::class,
                self::ROUTE_SIGNED,
            ));
        }

        return URL::temporarySignedRoute(self::ROUTE_SIGNED, now()->addMinutes(max(1, $minutes)), [
            'disk' => $disk,
            'path' => ltrim(str_replace('\\', '/', $path), '/'),
        ]);
    }

    /** @return list<string> */
    public static function collections(): array
    {
        return array_keys((array) config('xomiton.uploads.collections', []));
    }

    /** @return list<string> */
    public static function allowedMimes(string $collection): array
    {
        $config = self::collectionConfig($collection);

        return array_values(array_filter((array) ($config['mimes'] ?? []), 'is_string'));
    }

    public static function maxKb(string $collection): int
    {
        return max(1, (int) (self::collectionConfig($collection)['max_kb'] ?? 1024));
    }

    public static function privateDisk(): string
    {
        return (string) config('xomiton.uploads.private_disk', 'local');
    }

    public static function publicDisk(): string
    {
        return (string) config('xomiton.uploads.public_disk', 'public');
    }

    /** @return list<string> */
    public static function signedDisks(): array
    {
        return array_values(array_filter((array) config('xomiton.uploads.signed_disks', ['local']), 'is_string'));
    }

    /**
     * Type MIME réel, lu dans le contenu (libmagic) ; jamais celui annoncé par le navigateur ni l'extension.
     */
    public static function detectMime(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo === false ? false : finfo_file($finfo, $path);

        if ($finfo !== false) {
            finfo_close($finfo);
        }

        return is_string($mime) && $mime !== '' ? strtolower(trim(explode(';', $mime)[0])) : 'application/octet-stream';
    }

    /** @return array<string, mixed> */
    private static function collectionConfig(string $collection): array
    {
        $config = config('xomiton.uploads.collections.'.$collection);

        if (! is_array($config) || ! preg_match('/^[a-z][a-z0-9_-]*$/', $collection)) {
            throw new InvalidArgumentException(sprintf('Unknown upload collection [%s]; see xomiton.uploads.collections.', $collection));
        }

        return $config;
    }

    /**
     * Nom d'origine nettoyé (affichage uniquement) : sans chemin ni caractères de contrôle, 255 caractères au plus.
     */
    private static function displayName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', (string) $file->getClientOriginalName()));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name);

        return Str::limit(trim($name), 255, '') ?: 'fichier';
    }

    /** @return array{int|null, int|null} */
    private static function dimensions(string $path): array
    {
        $info = @getimagesize($path);

        return $info === false ? [null, null] : [(int) $info[0], (int) $info[1]];
    }

    // --- Images -----------------------------------------------------------------

    private function storeImage(UploadedFile $file, string $mime, string $disk, string $directory, string $ulid, string $originalName): StoredMedia
    {
        $sourcePath = (string) $file->getRealPath();
        $image = self::decode($sourcePath, $mime);

        if ($mime === 'image/jpeg') {
            $image = self::applyExifOrientation($image, $sourcePath);
        }

        $image = self::fit($image, (int) config('xomiton.uploads.image_max_dimension', 2048));

        $useWebp = (bool) config('xomiton.uploads.image_webp', true) && function_exists('imagewebp');
        $nativeFormat = $mime === 'image/jpeg' ? 'jpg' : 'png';
        $primaryFormat = $useWebp ? 'webp' : $nativeFormat;
        $fallbackFormat = $useWebp && (bool) config('xomiton.uploads.image_keep_fallback', true) ? $nativeFormat : null;
        $storage = Storage::disk($disk);

        $main = $this->writeImage($storage, $disk, $image, $directory.'/'.$ulid, $primaryFormat, $fallbackFormat, $originalName);

        $variants = [];
        $longSide = max(imagesx($image), imagesy($image));

        foreach ((array) config('xomiton.uploads.image_variants', []) as $name => $size) {
            $size = (int) $size;

            if (! is_string($name) || $size < 1 || $longSide <= $size) {
                continue;
            }

            $resized = self::fit($image, $size);
            $variants[$name] = $this->writeImage($storage, $disk, $resized, $directory.'/'.$ulid.'-'.$name, $primaryFormat, $fallbackFormat, $originalName);
            imagedestroy($resized);
        }

        imagedestroy($image);

        return new StoredMedia(
            $main->disk, $main->path, $main->mime, $main->size, $main->width, $main->height, $originalName, $main->fallbackPath, $variants,
        );
    }

    /**
     * Encode et écrit une image (et son repli), puis renvoie sa description.
     */
    private function writeImage(Filesystem $storage, string $disk, GdImage $image, string $stem, string $format, ?string $fallbackFormat, string $originalName): StoredMedia
    {
        $quality = (int) config('xomiton.uploads.image_quality', 82);
        $bytes = self::encode($image, $format, $quality);
        $path = $stem.'.'.$format;

        if (! $storage->put($path, $bytes)) {
            throw new RuntimeException(sprintf('Unable to store image on disk [%s] at [%s].', $disk, $path));
        }

        $fallbackPath = null;

        if ($fallbackFormat !== null && $fallbackFormat !== $format) {
            $fallbackPath = $stem.'.'.$fallbackFormat;

            if (! $storage->put($fallbackPath, self::encode($image, $fallbackFormat, $quality))) {
                throw new RuntimeException(sprintf('Unable to store image fallback on disk [%s] at [%s].', $disk, $fallbackPath));
            }
        }

        return new StoredMedia(
            $disk, $path, self::mimeForFormat($format), strlen($bytes), imagesx($image), imagesy($image), $originalName, $fallbackPath,
        );
    }

    /**
     * Décodage GD : l'image repart d'une matrice de pixels nue (plus aucune métadonnée), en couleurs vraies avec alpha.
     */
    private static function decode(string $path, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };

        if (! $image instanceof GdImage) {
            throw MediaRejected::corruptImage();
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    /**
     * Applique l'orientation EXIF (1 à 8) pour que l'image ré-encodée s'affiche droite sans métadonnée.
     */
    private static function applyExifOrientation(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        if ($orientation < 2 || $orientation > 8) {
            return $image;
        }

        $rotation = match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        if ($rotation !== 0) {
            $rotated = imagerotate($image, $rotation, 0);

            if ($rotated instanceof GdImage) {
                imagedestroy($image);
                $image = $rotated;
                imagealphablending($image, false);
                imagesavealpha($image, true);
            }
        }

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        return $image;
    }

    /**
     * Ramène le côté long à `$max` pixels (jamais d'agrandissement), en conservant la transparence.
     */
    private static function fit(GdImage $image, int $max): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($max < 1 || max($width, $height) <= $max) {
            return $image;
        }

        $ratio = $max / max($width, $height);
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $resized = imagecreatetruecolor($newWidth, $newHeight);

        if (! $resized instanceof GdImage) {
            throw new RuntimeException('Unable to allocate the resized image.');
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefill($resized, 0, 0, $transparent === false ? 0 : $transparent);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $resized;
    }

    private static function encode(GdImage $image, string $format, int $quality): string
    {
        ob_start();

        $ok = match ($format) {
            'webp' => imagewebp($image, null, max(0, min(100, $quality))),
            'jpg' => self::encodeJpeg($image, $quality),
            'png' => imagepng($image, null, 6),
            default => false,
        };

        $bytes = (string) ob_get_clean();

        if (! $ok || $bytes === '') {
            throw new RuntimeException(sprintf('Unable to encode image as [%s].', $format));
        }

        return $bytes;
    }

    /**
     * JPEG sans canal alpha : la transparence éventuelle est aplatie sur blanc plutôt que sur noir.
     */
    private static function encodeJpeg(GdImage $image, int $quality): bool
    {
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));

        if (! $flat instanceof GdImage) {
            return false;
        }

        $white = imagecolorallocate($flat, 255, 255, 255);
        imagefill($flat, 0, 0, $white === false ? 0 : $white);
        imagealphablending($flat, true);
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        $ok = imagejpeg($flat, null, max(0, min(100, $quality)));
        imagedestroy($flat);

        return $ok;
    }

    private static function mimeForFormat(string $format): string
    {
        return match ($format) {
            'webp' => 'image/webp',
            'jpg' => 'image/jpeg',
            default => 'image/png',
        };
    }
}
