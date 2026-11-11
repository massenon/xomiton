<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\DirectoryRole;
use App\Models\DirectoryContact;
use App\Models\Zone;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Annuaire du prototype (prototype_social/data/annuaire.json, 10 contacts) recréé avec les **mêmes identifiants** pour
 * rejouer `route_alert()` (api.php l.12-25) contre AlertRouter. Les départements manquants sont créés.
 */
final class PrototypeDirectory
{
    public const FILE = __DIR__.'/../../../prototype_social/data/annuaire.json';

    /**
     * @return array<int, DirectoryContact> identifiant du prototype => fiche
     */
    public static function create(): array
    {
        $rows = json_decode((string) file_get_contents(self::FILE), true);

        if (! is_array($rows)) {
            throw new RuntimeException('annuaire.json illisible');
        }

        $contacts = [];

        foreach ($rows as $row) {
            $role = DirectoryRole::tryFromLabel((string) $row['role']) ?? throw new RuntimeException('Rôle inconnu : '.$row['role']);

            $contacts[(int) $row['id']] = DirectoryContact::factory()->create([
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'role' => $role,
                'zone_id' => self::zone((string) $row['zone'])->id,
                'is_active' => (bool) $row['active'],
                'is_public' => true,
                'is_national_focal' => false,
            ]);
        }

        return $contacts;
    }

    public static function zone(string $name): Zone
    {
        return Zone::query()->where('name', $name)->first()
            ?? Zone::factory()->create(['name' => $name, 'slug' => Str::slug($name)]);
    }
}
