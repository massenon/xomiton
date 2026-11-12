<?php

declare(strict_types=1);

namespace App\Application\Content\ViolencesNumeriques;

use JsonException;
use RuntimeException;

/**
 * Contenus produits des violences numériques (P19, Q98) lus dans `database/seeders/data/content/violences_numeriques/` :
 * `_categories.json` (6 catégories, 23 sujets, statut `pilote` ou `a_venir`) et un fichier `<slug>.json` par sujet pilote
 * (BD, fiche, FAQ, visuel de partage). Lecture stricte : un fichier absent ou mal formé lève une RuntimeException
 * (l'import s'arrête avant toute écriture).
 */
final class ViolencesNumeriquesSource
{
    public const STATUS_PILOT = 'pilote';

    public const STATUS_UPCOMING = 'a_venir';

    public function __construct(private readonly ?string $directory = null) {}

    public function directory(): string
    {
        return $this->directory ?? database_path('seeders/data/content/violences_numeriques');
    }

    /**
     * @return list<array{slug: string, label: string, icon: string, topics: list<array{slug: string, title: string, summary: string, status: string, key: string|null}>}>
     */
    public function categories(): array
    {
        $data = $this->read('_categories.json');
        $categories = [];

        foreach ((array) ($data['categories'] ?? []) as $category) {
            if (! is_array($category)) {
                continue;
            }

            $topics = [];
            foreach ((array) ($category['topics'] ?? []) as $topic) {
                if (! is_array($topic) || ! is_string($topic['slug'] ?? null) || ! is_string($topic['title'] ?? null)) {
                    throw new RuntimeException('Sujet mal formé dans _categories.json.');
                }

                $topics[] = [
                    'slug' => $topic['slug'],
                    'title' => $topic['title'],
                    'summary' => (string) ($topic['summary'] ?? ''),
                    'status' => ($topic['status'] ?? '') === self::STATUS_PILOT ? self::STATUS_PILOT : self::STATUS_UPCOMING,
                    'key' => is_string($topic['key'] ?? null) ? $topic['key'] : null,
                ];
            }

            $categories[] = [
                'slug' => (string) ($category['slug'] ?? ''),
                'label' => (string) ($category['label'] ?? ''),
                'icon' => (string) ($category['icon'] ?? ''),
                'topics' => $topics,
            ];
        }

        if ($categories === []) {
            throw new RuntimeException('Aucune catégorie dans _categories.json.');
        }

        return $categories;
    }

    /**
     * Sujet pilote complet (`<slug>.json`).
     *
     * @return array<string, mixed>
     */
    public function topic(string $slug): array
    {
        $data = $this->read($slug.'.json');

        foreach (['key', 'title', 'summary', 'bd', 'fiche', 'faq', 'share'] as $key) {
            if (! array_key_exists($key, $data)) {
                throw new RuntimeException("Clé « {$key} » absente de {$slug}.json.");
            }
        }

        return $data;
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
