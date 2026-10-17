<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Enums\GroupPrivacy;
use App\Models\Group;
use Illuminate\Support\Str;

/**
 * groups.json → groups (name → slug, desc → description, members → members_count, privacy « Privé » /
 * « Public », image → image_path, couverture de marque gr-N.jpg, zone « National » → null).
 * Clé naturelle : le slug du nom.
 */
final class ImportGroups extends AbstractImporter
{
    public static function collection(): string
    {
        return 'groups';
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

        $prototypeId = (int) ($row['id'] ?? 0);

        $group = Group::withTrashed()->firstOrNew(['slug' => $slug]);

        $group->fill([
            'name' => $name,
            'description' => $this->text($row['desc'] ?? $row['description'] ?? null),
            'privacy' => GroupPrivacy::fromLabel((string) ($row['privacy'] ?? 'Public')),
            'zone_id' => $this->ctx->zoneId($row['zone'] ?? null),
            'image_path' => $this->imagePath($row['image'] ?? null),
            'cover_path' => 'assets/images/xomiton/covers/'.($prototypeId >= 1 && $prototypeId <= 6 ? 'gr-'.$prototypeId : 'gr-default').'.jpg',
            'members_count' => max(0, (int) ($row['members'] ?? 0)),
            'is_active' => true,
        ]);
        $group->deleted_at = null;
        $group->save();

        $this->ctx->remember('groups', $this->prototypeId($row, $index), (int) $group->getKey());
        $this->track($group);
    }
}
