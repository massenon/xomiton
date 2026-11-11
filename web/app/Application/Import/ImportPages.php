<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Models\Page;
use Illuminate\Support\Str;

/**
 * pages.json → pages (name → slug, category, followers → followers_count, image → image_path,
 * desc → description). Clé naturelle : le slug du nom. Pages d'organisations partenaires choisies par l'équipe :
 * importées approuvées à leur création (P8-03 ; une page créée par un·e membre attend la modération, P12-05, et n'est
 * jamais reprise par l'import : ligne rejetée si son slug est déjà pris par une page de membre).
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

        // Page d'un·e membre au même slug : ni écrasée ni approuvée sans modération (P12-05).
        if ($page->exists && $page->owner_id !== null) {
            $this->reject(sprintf('slug « %s » déjà pris par une page de membre', $slug));
        }

        $approve = ! $page->exists;

        $page->fill([
            'name' => $name,
            'category' => $this->text($row['category'] ?? null, 60) ?? 'Organisation',
            'description' => $this->text($row['desc'] ?? $row['description'] ?? null),
            'image_path' => $this->imagePath($row['image'] ?? null),
            'followers_count' => max(0, (int) ($row['followers'] ?? 0)),
            'is_active' => true,
        ]);

        // Approuvée à sa création seulement : une réimportation ne revient pas sur une décision de la modération.
        if ($approve) {
            $page->forceFill(['is_approved' => true, 'approved_at' => now()]);
        }
        $page->deleted_at = null;
        $page->save();

        $this->ctx->remember('pages', $this->prototypeId($row, $index), (int) $page->getKey());
        $this->track($page);
    }
}
