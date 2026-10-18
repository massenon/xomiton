<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Application\Shared\Referentials;
use App\Models\Category;

/**
 * Puces de catégorie des listes publiques (règle Q60 de `/ressources`, reprise par `/actualites` P4-10) : catégories
 * actives des types `thematique` et `resource` du référentiel en cache (Referentials), triées par position puis
 * libellé, regroupées par slug (un même slug dans les deux types = une puce), avec le nombre de contenus publiés.
 * Copie de ListResources::chips(), à reprendre par P4-03.
 *
 * @phpstan-type Chip array{ids: list<int>, slug: string, label: string, icon: string|null, color: string|null, position: int, count: int}
 */
final class CategoryChips
{
    public function __construct(private readonly Referentials $referentials) {}

    /**
     * Toutes les puces (compte nul compris : un filtre valide sans contenu reste servi).
     *
     * @param  array<int|string, int>  $counts  nombre de contenus publiés par `category_id`
     * @return list<Chip>
     */
    public function all(array $counts): array
    {
        $categories = array_values(array_filter(
            $this->referentials->categories(),
            static fn (array $category): bool => in_array($category['kind'], [Category::KIND_THEMATIQUE, Category::KIND_RESOURCE], true),
        ));
        usort($categories, static fn (array $a, array $b): int => [$a['position'], $a['label'], $a['kind']] <=> [$b['position'], $b['label'], $b['kind']]);

        $chips = [];
        foreach ($categories as $category) {
            $count = $counts[$category['id']] ?? 0;

            if (isset($chips[$category['slug']])) {
                $chips[$category['slug']]['ids'][] = $category['id'];
                $chips[$category['slug']]['count'] += $count;

                continue;
            }

            $chips[$category['slug']] = [
                'ids' => [$category['id']],
                'slug' => $category['slug'],
                'label' => $category['label'],
                'icon' => $category['icon'],
                'color' => $category['color'],
                'position' => $category['position'],
                'count' => $count,
            ];
        }

        return array_values($chips);
    }

    /**
     * Puces affichées : celles qui ont au moins un contenu publié.
     *
     * @param  list<Chip>  $chips
     * @return list<Chip>
     */
    public static function visible(array $chips): array
    {
        return array_values(array_filter($chips, static fn (array $chip): bool => $chip['count'] > 0));
    }

    /**
     * Identifiants de toutes les catégories listées (badge d'une carte, maillon du fil d'Ariane).
     *
     * @param  list<Chip>  $chips
     * @return list<int>
     */
    public static function ids(array $chips): array
    {
        return array_merge([], ...array_column($chips, 'ids'));
    }
}
