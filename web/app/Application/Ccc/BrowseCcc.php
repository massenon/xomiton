<?php

declare(strict_types=1);

namespace App\Application\Ccc;

use App\Enums\CccKind;
use App\Models\CccTool;
use App\Models\Law;
use Illuminate\Support\Collection;

/**
 * Lecture des outils CCC par les membres et les pair·es (P8-12, `GET /api/v1/ccc`, `GET /api/v1/ccc/{key}` et page
 * `/ccc` ; prototype ccc.php, manuel-pair-animateur.php, boites-a-images.php, bd-ssr.php) : modules du manuel,
 * boîtes à images et bandes dessinées SSR **publiés seulement** (`is_published`, jamais un outil masqué dans
 * `/admin/ccc`), dans l'ordre `position` puis clé, rangés par famille ; les textes de loi restent dans leur table
 * (`laws`) et sont seulement comptés ici, la page `/lois` les affiche. Lecture seule, une seule requête par appel.
 */
final class BrowseCcc
{
    /** Familles servies, dans l'ordre des onglets (la famille « loi » de l'enum n'a pas de ligne : table `laws`). */
    public const FAMILIES = [
        'manuel' => CccKind::Manual,
        'boites_images' => CccKind::ImageBox,
        'bd' => CccKind::Comic,
    ];

    /**
     * @return array<string, Collection<int, CccTool>> famille (`manuel`, `boites_images`, `bd`) → outils publiés
     */
    public function families(): array
    {
        $tools = CccTool::query()->published()->ordered()
            ->whereIn('kind', array_map(static fn (CccKind $kind): string => $kind->value, array_values(self::FAMILIES)))
            ->get();

        $grouped = [];

        foreach (self::FAMILIES as $family => $kind) {
            $grouped[$family] = $tools
                ->filter(static fn (CccTool $tool): bool => (string) $tool->getRawOriginal('kind') === $kind->value)
                ->values();
        }

        return $grouped;
    }

    /** Outil publié par sa clé publique ; masqué, inconnu ou d'une autre famille (loi) : null. */
    public function find(string $key): ?CccTool
    {
        $tool = CccTool::query()->published()->where('key', $key)->first();

        return $tool !== null && self::familyOf($tool) !== null ? $tool : null;
    }

    /** Famille d'un outil (`manuel`, `boites_images`, `bd`), null pour un kind sans lecteur. */
    public static function familyOf(CccTool $tool): ?string
    {
        $kind = CccKind::tryFrom((string) $tool->getRawOriginal('kind'));

        foreach (self::FAMILIES as $family => $served) {
            if ($served === $kind) {
                return $family;
            }
        }

        return null;
    }

    /** Textes de loi publiés (lien vers `/lois`). */
    public function publishedLaws(): int
    {
        return Law::query()->published()->count();
    }
}
