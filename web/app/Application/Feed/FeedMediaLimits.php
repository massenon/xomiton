<?php

declare(strict_types=1);

namespace App\Application\Feed;

use App\Application\Shared\MediaUploader;

/**
 * Plafonds des médias d'une publication (Q96), lus au moment de la requête : nombre d'images (`feed.max_images`) et poids
 * d'une vidéo (`feed.video_max_mb`, **borné par PHP** : `upload_max_filesize` et `post_max_size`, sinon le serveur
 * rejetterait le fichier avant toute validation). Partagé par StorePostRequest, FeedComposerOptions et la documentation
 * d'API, pour que le navigateur et l'application annoncent la même limite que le serveur applique.
 */
final class FeedMediaLimits
{
    public static function maxImages(): int
    {
        return max(1, (int) config('xomiton.feed.max_images', 4));
    }

    /** Poids maximal d'une vidéo en Ko : min(configuration, collection `video`, upload_max_filesize, post_max_size). */
    public static function videoMaxKb(): int
    {
        $limits = [
            max(1, (int) config('xomiton.feed.video_max_mb', 30)) * 1024,
            MediaUploader::maxKb('video'),
        ];

        foreach (['upload_max_filesize', 'post_max_size'] as $directive) {
            $bytes = self::iniBytes((string) ini_get($directive));

            if ($bytes > 0) {
                $limits[] = intdiv($bytes, 1024);
            }
        }

        return max(1, min($limits));
    }

    /** @return list<string> */
    public static function videoMimes(): array
    {
        return MediaUploader::allowedMimes('video');
    }

    public static function descriptionMin(): int
    {
        return max(1, (int) config('xomiton.feed.video_description_min', 5));
    }

    public static function descriptionMax(): int
    {
        return max(self::descriptionMin(), (int) config('xomiton.feed.video_description_max', 500));
    }

    /** Valeur d'une directive PHP (« 8M », « 512K », « 1G », « -1 ») en octets ; 0 si illimitée ou illisible. */
    public static function iniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || ! preg_match('/^(-?\d+)\s*([kmg]?)b?$/i', $value, $matches)) {
            return 0;
        }

        $number = (int) $matches[1];

        if ($number <= 0) {
            return 0;
        }

        return $number * match (strtolower($matches[2])) {
            'k' => 1024,
            'm' => 1024 ** 2,
            'g' => 1024 ** 3,
            default => 1,
        };
    }
}
