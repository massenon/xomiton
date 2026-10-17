<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Résout l'URL publique d'une image stockée en base (`avatar_path`, `cover_path`, `image_path`…)
 * en garantissant qu'elle pointe sur un fichier existant : URL absolue conservée telle quelle,
 * chemin `assets/…` servi depuis `public/`, sinon fichier du disque `public` (téléversements) ;
 * à défaut, l'image par défaut fournie. Évite les 404 quand un fichier de démonstration manque.
 */
final class MediaUrl
{
    public static function resolve(?string $path, string $default): string
    {
        $path = self::normalize($path);

        if ($path === '') {
            return asset($default);
        }

        if (self::isAbsolute($path)) {
            return $path;
        }

        if (! self::exists($path)) {
            return asset($default);
        }

        return str_starts_with($path, 'assets/') ? asset($path) : Storage::disk('public')->url($path);
    }

    /**
     * Le fichier désigné existe-t-il (dans public/ pour `assets/…`, sinon sur le disque public) ?
     */
    public static function exists(?string $path): bool
    {
        $path = self::normalize($path);

        if ($path === '') {
            return false;
        }

        if (self::isAbsolute($path)) {
            return true;
        }

        if (str_starts_with($path, 'assets/')) {
            return is_file(public_path($path));
        }

        return Storage::disk('public')->exists($path);
    }

    private static function normalize(?string $path): string
    {
        $path = trim((string) $path);

        return self::isAbsolute($path) ? $path : ltrim($path, '/');
    }

    private static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
    }
}
