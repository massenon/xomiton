<?php

declare(strict_types=1);

namespace App\Application\Gateways;

use Throwable;

/**
 * Image de `public/assets/` préparée pour l'envoi à un réseau (P19-11). Ni la Graph API (photos de page : JPEG, PNG, GIF,
 * BMP, TIFF) ni la Bot API (`sendPhoto` / `sendMediaGroup` : photos JPEG ou PNG) ne garantissent le WebP : un WebP est
 * converti **en mémoire** en JPEG (GD, sinon Imagick, disponibles sur cPanel), sans jamais écrire de fichier ; JPEG et
 * PNG partent tels quels. Seuls les fichiers réels sous `public/assets/` sont lus (aucun chemin remontant, aucun lien
 * externe).
 */
final class PublicImage
{
    /** Taille maximale envoyée (photo Telegram : 10 Mo ; Facebook : 4 Mo conseillés). */
    public const MAX_BYTES = 4 * 1024 * 1024;

    public const JPEG_QUALITY = 88;

    public static function exists(string $path): bool
    {
        return self::file($path) !== null;
    }

    /**
     * Contenu prêt à téléverser, sinon null (fichier absent, format non pris en charge, conversion impossible, trop lourd).
     *
     * @return array{bytes: string, mime: string, name: string}|null
     */
    public static function prepare(string $path): ?array
    {
        $file = self::file($path);

        if ($file === null) {
            return null;
        }

        $info = @getimagesize($file);
        $mime = is_array($info) ? (string) $info['mime'] : '';
        $base = pathinfo($file, PATHINFO_FILENAME);

        $prepared = match ($mime) {
            'image/jpeg' => ['bytes' => (string) file_get_contents($file), 'mime' => 'image/jpeg', 'name' => $base.'.jpg'],
            'image/png' => ['bytes' => (string) file_get_contents($file), 'mime' => 'image/png', 'name' => $base.'.png'],
            'image/webp' => self::webpToJpeg($file, $base),
            default => null,
        };

        if ($prepared === null || $prepared['bytes'] === '' || strlen($prepared['bytes']) > self::MAX_BYTES) {
            return null;
        }

        return $prepared;
    }

    /** Chemin absolu d'un fichier réel sous `public/assets/`, sinon null. */
    private static function file(string $path): ?string
    {
        $path = ltrim(trim($path), '/');

        if ($path === '' || ! str_starts_with($path, 'assets/') || str_contains($path, '..')) {
            return null;
        }

        $real = realpath(public_path($path));
        $root = realpath(public_path('assets'));

        return $real !== false && $root !== false && str_starts_with($real, $root.DIRECTORY_SEPARATOR) && is_file($real) ? $real : null;
    }

    /** @return array{bytes: string, mime: string, name: string}|null */
    private static function webpToJpeg(string $file, string $base): ?array
    {
        $bytes = null;

        if (function_exists('imagecreatefromwebp') && function_exists('imagejpeg')) {
            $image = @imagecreatefromwebp($file);

            if ($image !== false) {
                ob_start();
                $ok = imagejpeg($image, null, self::JPEG_QUALITY);
                $output = (string) ob_get_clean();
                $bytes = $ok ? $output : null;
            }
        }

        $class = 'Imagick'; // extension facultative : nom dynamique pour l'analyse statique

        if ($bytes === null && class_exists($class)) {
            try {
                $imagick = new $class($file);
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(self::JPEG_QUALITY);
                $bytes = (string) $imagick->getImageBlob();
                $imagick->clear();
            } catch (Throwable) {
                $bytes = null;
            }
        }

        return $bytes === null || $bytes === '' ? null : ['bytes' => $bytes, 'mime' => 'image/jpeg', 'name' => $base.'.jpg'];
    }
}
