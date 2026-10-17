<?php

declare(strict_types=1);

namespace App\Application\Shared;

/**
 * Résultat d'un téléversement par MediaUploader : où le fichier est stocké (disque + chemin), ce qu'il est vraiment
 * (type réel, poids, dimensions) et le nom d'origine, conservé pour l'affichage seulement — jamais pour nommer le fichier.
 * Pour une image ré-encodée : `path` est le WebP, `fallbackPath` le JPEG / PNG de repli et `variants` les tailles
 * dérivées (`thumb`, `medium`), chacune décrite par un StoredMedia ; tous partagent le même ULID, ce qui permet de les
 * supprimer ensemble.
 */
final readonly class StoredMedia
{
    /**
     * @param  array<string, StoredMedia>  $variants  nom de variante => média dérivé, sur le même disque
     */
    public function __construct(
        public string $disk,
        public string $path,
        public string $mime,
        public int $size,
        public ?int $width,
        public ?int $height,
        public string $originalName,
        public ?string $fallbackPath = null,
        public array $variants = [],
    ) {}

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function isPublic(): bool
    {
        return $this->disk === (string) config('xomiton.uploads.public_disk', 'public');
    }

    public function hasVariant(string $name): bool
    {
        return isset($this->variants[$name]);
    }

    /**
     * Variante demandée, ou l'image principale si la variante n'a pas été produite (image déjà plus petite).
     */
    public function variant(string $name): self
    {
        return $this->variants[$name] ?? $this;
    }

    /**
     * Tous les chemins écrits pour ce média (principal, repli, variantes et leurs replis), sans doublon.
     *
     * @return list<string>
     */
    public function allPaths(): array
    {
        $paths = [$this->path];

        if ($this->fallbackPath !== null) {
            $paths[] = $this->fallbackPath;
        }

        foreach ($this->variants as $variant) {
            $paths = [...$paths, ...$variant->allPaths()];
        }

        return array_values(array_unique($paths));
    }

    /**
     * Forme tableau, pour alimenter directement les colonnes `path`, `width`, `height`, `size` des modèles de médias.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'disk' => $this->disk,
            'path' => $this->path,
            'mime' => $this->mime,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'original_name' => $this->originalName,
            'fallback_path' => $this->fallbackPath,
            'variants' => array_map(static fn (self $variant): array => $variant->toArray(), $this->variants),
        ];
    }
}
