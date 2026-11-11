<?php

declare(strict_types=1);

namespace App\Application\Admin\Content;

use App\Domain\Content\Exceptions\ContentRefused;
use App\Models\Category;
use App\Models\User;
use App\Support\Audit;

/**
 * Catégories de contenu de l'administration (P12-03, permission `thematiques.manage`) : libellé, icône, couleur, ordre,
 * actif / inactif. Le type (`kind`) et le slug restent fixes après la création (les adresses `?cat=` et les référentiels
 * des applications s'y appuient). Une catégorie encore utilisée n'est jamais supprimée : on la désactive. Chaque écriture
 * invalide les référentiels (ReferentialObserver) et le plan du site.
 */
final class ManageCategories
{
    public function __construct(private readonly PublicContentCache $cache) {}

    /**
     * @param  array<string, mixed>  $data  données validées par ContentRules::category()
     */
    public function save(?Category $category, array $data, User $actor): Category
    {
        $category ??= new Category;
        $creating = ! $category->exists;

        $attributes = [
            'label' => trim((string) $data['label']),
            'icon' => self::nullable($data['icon'] ?? null),
            'color' => self::nullable($data['color'] ?? null),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if ($creating) {
            $attributes += [
                'kind' => (string) $data['kind'],
                'slug' => (string) $data['slug'],
                'position' => (int) Category::query()->where('kind', $data['kind'])->max('position') + 1,
            ];
        }

        $category->fill($attributes)->save();
        Audit::log($creating ? 'content.category_created' : 'content.category_updated', $category, $category->label, $actor);
        $this->cache->flush();

        return $category;
    }

    /**
     * Monter (−1) ou descendre (+1) dans l'ordre de son type.
     */
    public function move(Category $category, int $direction, User $actor): void
    {
        $ordered = Category::query()->where('kind', $category->kind)->ordered()->get(['id', 'position']);
        $index = $ordered->search(fn (Category $row): bool => $row->getKey() === $category->getKey());
        $target = $index === false ? null : $index + ($direction < 0 ? -1 : 1);

        if ($index === false || $ordered->get($target) === null) {
            return;
        }

        $ids = $ordered->pluck('id')->all();
        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        foreach ($ids as $position => $id) {
            Category::query()->whereKey($id)->update(['position' => $position + 1]);
        }

        Audit::log('content.category_moved', $category, $category->label, $actor);
        $this->cache->flush();
    }

    /**
     * @throws ContentRefused si la catégorie est utilisée
     */
    public function delete(Category $category, User $actor): void
    {
        if (self::usage($category) > 0) {
            throw new ContentRefused('category_in_use');
        }

        $category->delete();
        Audit::log('content.category_deleted', $category, $category->label, $actor);
        $this->cache->flush();
    }

    /** Nombre de contenus rattachés (thématiques, ressources, questions, événements). */
    public static function usage(Category $category): int
    {
        return $category->thematiques()->withTrashed()->count()
            + $category->resources()->withTrashed()->count()
            + $category->faqs()->count()
            + $category->events()->withTrashed()->count();
    }

    private static function nullable(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
