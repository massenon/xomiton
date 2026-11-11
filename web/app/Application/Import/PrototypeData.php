<?php

declare(strict_types=1);

namespace App\Application\Import;

use InvalidArgumentException;
use RuntimeException;

/**
 * Lecture du mini-stockage JSON du prototype (prototype_social/inc/store.php : un fichier
 * data/<collection>.json par collection, liste d'objets avec un champ `id`).
 * Les sous-dossiers `seed/` et `backups/` ne sont jamais lus ; les collections `stock*` sont ignorées.
 */
final class PrototypeData
{
    public readonly string $directory;

    /** @var array<string, list<array<string, mixed>>> */
    private array $cache = [];

    public function __construct(string $directory)
    {
        $real = realpath($directory);

        if ($real === false || ! is_dir($real)) {
            throw new InvalidArgumentException(sprintf('Le dossier « %s » n’existe pas.', $directory));
        }

        $this->directory = $real;
    }

    /** Une collection `stock*` (stocks du prototype) n'est jamais importée. */
    public static function isIgnored(string $collection): bool
    {
        return str_starts_with(mb_strtolower($collection), 'stock');
    }

    /** Chemin du fichier d'une collection, nettoyé comme `store_path()` du prototype. */
    public function path(string $collection): string
    {
        return $this->directory.'/'.preg_replace('/[^a-z0-9_-]/i', '', $collection).'.json';
    }

    public function has(string $collection): bool
    {
        return ! self::isIgnored($collection) && is_file($this->path($collection));
    }

    /**
     * Lignes d'une collection ; vide si le fichier est absent, exception si le JSON est invalide.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(string $collection): array
    {
        if (array_key_exists($collection, $this->cache)) {
            return $this->cache[$collection];
        }

        if (! $this->has($collection)) {
            return $this->cache[$collection] = [];
        }

        $decoded = json_decode((string) file_get_contents($this->path($collection)), true);

        if (! is_array($decoded)) {
            throw new RuntimeException(sprintf('Le fichier %s ne contient pas une liste JSON valide.', $this->path($collection)));
        }

        $rows = array_values(array_filter($decoded, 'is_array'));

        return $this->cache[$collection] = $rows;
    }

    /**
     * Collections présentes dans le dossier (hors `stock*`), par nom de fichier.
     *
     * @return list<string>
     */
    public function available(): array
    {
        $names = [];

        foreach (glob($this->directory.'/*.json') ?: [] as $file) {
            $name = basename($file, '.json');

            if (! self::isIgnored($name)) {
                $names[] = $name;
            }
        }

        sort($names);

        return $names;
    }

    /**
     * Fichier d'un média de démonstration du prototype (`assets/images/xomiton/<fichier>`) : d'abord à côté
     * du dossier data/ importé, sinon dans public/ de l'application qui reprend les mêmes images.
     */
    public function assetPath(string $relative): ?string
    {
        $relative = ltrim($relative, '/');

        foreach ([dirname($this->directory).'/assets/'.$relative, public_path('assets/'.$relative)] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
