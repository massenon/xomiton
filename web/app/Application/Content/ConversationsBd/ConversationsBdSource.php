<?php

declare(strict_types=1);

namespace App\Application\Content\ConversationsBd;

use JsonException;
use RuntimeException;

/**
 * Données des conversations en BD (P20-05) lues dans `database/seeders/data/content/conversations_bd/` : `_casting.json`
 * (personnages), `_scenes.json` (bibliothèque de scènes : image, description, personnages, zone des bulles) et un fichier
 * `mNN-<slug>.json` par modèle (MODELES.md converti ; cases → `{scene, description, bulles}` au format de la grammaire,
 * §6). Lecture stricte : fichier absent ou mal formé → RuntimeException avant toute écriture.
 */
final class ConversationsBdSource
{
    public function __construct(private readonly ?string $directory = null) {}

    public function directory(): string
    {
        return $this->directory ?? database_path('seeders/data/content/conversations_bd');
    }

    /** @return list<array{key: string, name: string, pronoun: string|null, role: string|null, description: string|null, secondary: bool}> */
    public function characters(): array
    {
        $rows = [];

        foreach ((array) ($this->read('_casting.json')['characters'] ?? []) as $row) {
            if (! is_array($row) || ! is_string($row['key'] ?? null) || ! is_string($row['name'] ?? null)) {
                throw new RuntimeException('Personnage mal formé dans _casting.json.');
            }

            $rows[] = [
                'key' => $row['key'],
                'name' => $row['name'],
                'pronoun' => is_string($row['pronoun'] ?? null) ? $row['pronoun'] : null,
                'role' => is_string($row['role'] ?? null) ? $row['role'] : null,
                'description' => is_string($row['description'] ?? null) ? $row['description'] : null,
                'secondary' => (bool) ($row['secondary'] ?? false),
            ];
        }

        return $rows;
    }

    /** @return list<array{key: string, title: string|null, image: string|null, description: string, characters: list<string>, zone: string|null}> */
    public function scenes(): array
    {
        $rows = [];

        foreach ((array) ($this->read('_scenes.json')['scenes'] ?? []) as $row) {
            if (! is_array($row) || ! is_string($row['key'] ?? null) || ! is_string($row['description'] ?? null)) {
                throw new RuntimeException('Scène mal formée dans _scenes.json.');
            }

            $rows[] = [
                'key' => $row['key'],
                'title' => is_string($row['title'] ?? null) ? $row['title'] : null,
                'image' => is_string($row['image'] ?? null) && $row['image'] !== '' ? $row['image'] : null,
                'description' => $row['description'],
                'characters' => array_values(array_filter((array) ($row['personnages'] ?? []), 'is_string')),
                'zone' => is_string($row['zone'] ?? null) ? $row['zone'] : null,
            ];
        }

        return $rows;
    }

    /**
     * Modèles, dans l'ordre des fichiers (m01, m02…).
     *
     * @return list<array<string, mixed>>
     */
    public function models(): array
    {
        $files = glob(rtrim($this->directory(), '/').'/m[0-9][0-9]-*.json') ?: [];
        sort($files);
        $models = [];

        foreach ($files as $path) {
            $data = $this->read(basename($path));

            foreach (['key', 'slug', 'title', 'cases'] as $key) {
                if (! array_key_exists($key, $data)) {
                    throw new RuntimeException('Clé « '.$key.' » absente de '.basename($path).'.');
                }
            }

            if (! is_array($data['cases']) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $data['slug']) !== 1) {
                throw new RuntimeException('Modèle mal formé : '.basename($path).'.');
            }

            $models[] = $data;
        }

        if ($models === []) {
            throw new RuntimeException('Aucun modèle de conversation en BD.');
        }

        return $models;
    }

    /** @return array<string, mixed> */
    private function read(string $file): array
    {
        $path = rtrim($this->directory(), '/').'/'.$file;

        if (! is_file($path)) {
            throw new RuntimeException("Fichier introuvable : {$file}.");
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException("JSON invalide : {$file} ({$error->getMessage()}).", 0, $error);
        }

        if (! is_array($data)) {
            throw new RuntimeException("JSON inattendu : {$file}.");
        }

        return $data;
    }
}
