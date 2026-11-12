<?php

declare(strict_types=1);

namespace App\Http\Resources\Ccc;

use App\Application\Ccc\BrowseCcc;
use App\Models\CccTool;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Outil CCC publié (P8-12, docs/API.md §19 bis) : `key` (`m1`, `bi1`, `bd1`), `family` (`manuel`, `boites_images`, `bd`),
 * titre, description (consigne de l'animateur·rice d'une boîte à images, présentation d'un module ou d'une BD), `cover`
 * (adresse de l'image, image de remplacement si le fichier manque). Module du manuel : `duration_minutes` et `chapters`
 * (textes dans l'ordre stocké). Boîte à images et BD : `pages` (`{position, image, caption, alt}` dans l'ordre stocké,
 * `alt` jamais vide : à défaut, le titre). BD : `public_url` (P19-04) = adresse de sa page publique `/bd/{key}` si elle est
 * publique (`is_public`), sinon null. Jamais la position d'ordre ni l'état de publication (seuls les outils publiés sont servis).
 *
 * @property CccTool $resource
 */
final class CccToolResource extends JsonResource
{
    /** Image de remplacement : celle de l'administration (`/admin/ccc`). */
    public const FALLBACK_IMAGE = 'assets/images/xomiton/covers/og-default.jpg';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tool = $this->resource;
        $family = BrowseCcc::familyOf($tool);
        $title = (string) $tool->title;
        $description = trim((string) $tool->description);
        $out = [
            'key' => (string) $tool->key,
            'family' => $family,
            'title' => $title,
            'description' => $description === '' ? null : $description,
            'cover' => $family === 'manuel' ? null : MediaUrl::resolve($tool->image_path, self::FALLBACK_IMAGE),
        ];

        if ($family === 'manuel') {
            $chapters = self::chapters($tool);
            $out['duration_minutes'] = $tool->duration_minutes;
            $out['chapters'] = $chapters;
            $out['chapters_count'] = count($chapters);

            return $out;
        }

        $pages = self::pages($tool, $title);
        $out['pages'] = $pages;
        $out['pages_count'] = count($pages);

        if ($family === 'bd') {
            $out['public_url'] = $tool->is_public ? route('bd.show', ['key' => (string) $tool->key]) : null;
        }

        return $out;
    }

    /**
     * Chapitres : textes simples non vides, dans l'ordre stocké.
     *
     * @return list<string>
     */
    private static function chapters(CccTool $tool): array
    {
        $chapters = [];

        foreach ((array) $tool->chapters as $chapter) {
            if (is_string($chapter) && trim($chapter) !== '') {
                $chapters[] = trim($chapter);
            }
        }

        return $chapters;
    }

    /**
     * Planches `{position, image, caption, alt}` dans l'ordre stocké (rang à partir de 1).
     *
     * @return list<array{position: int, image: string, caption: string|null, alt: string}>
     */
    private static function pages(CccTool $tool, string $title): array
    {
        $pages = [];

        foreach ((array) $tool->pages as $page) {
            if (! is_array($page)) {
                continue;
            }

            $position = count($pages) + 1;
            $image = $page['image'] ?? null;
            $caption = $page['caption'] ?? null;
            $alt = $page['alt'] ?? null;
            $caption = is_string($caption) && trim($caption) !== '' ? trim($caption) : null;
            $alt = is_string($alt) && trim($alt) !== '' ? trim($alt) : $title.' – '.$position;

            $pages[] = [
                'position' => $position,
                'image' => MediaUrl::resolve(is_string($image) ? $image : null, self::FALLBACK_IMAGE),
                'caption' => $caption,
                'alt' => $alt,
            ];
        }

        return $pages;
    }
}
