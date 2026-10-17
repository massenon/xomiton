<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Models\Page;
use Illuminate\Support\Str;

/**
 * pages.json → pages (name → slug, category, followers → followers_count, image → image_path,
 * desc → description). Clé naturelle : le slug du nom.
 */
final class ImportPages extends AbstractImporter
{
    public static function collection(): string
    {
        return 'pages';
    }

    public static function isDemo(): bool
    {
        return true;
    }

    protected function importRow(array $row, int $index): void
    {
        $name = $this->required($row['name'] ?? null, 'name', 120);
        $slug = Str::slug($name);

        if ($slug === '') {
            $this->reject(sprintf('nom « %s » sans slug possible', $name));
        }

        $page = Page::withTrashed()->firstOrNew(['slug' => $slug]);

        $page->fill([
            'name' => $name,
            'category' => $this->text($row['category'] ?? null, 60) ?? 'Organisation',
            'description' => $this->text($row['desc'] ?? $row['description'] ?? null),
            'image_path' => $this->imagePath($row['image'] ?? null),
            'followers_count' => max(0, (int) ($row['followers'] ?? 0)),
            'is_active' => true,
        ]);
        $page->deleted_at = null;
        $page->save();

        $this->ctx->remember('pages', $this->prototypeId($row, $index), (int) $page->getKey());
        $this->track($page);
    }
}
