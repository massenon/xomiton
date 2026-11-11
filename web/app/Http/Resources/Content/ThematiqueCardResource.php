<?php

declare(strict_types=1);

namespace App\Http\Resources\Content;

use App\Models\Category;
use App\Models\Thematique;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Carte de fiche thématique (P6-08 : `GET thematiques`, « À la une » du tableau de bord, fiches liées) : titre, résumé,
 * couverture, durée de lecture, catégorie (seulement si elle est un filtre de la liste publique : jamais un slug qui
 * répondrait 404 à `?categorie=`), lien public canonique. Ni corps ni compteur de consultations.
 *
 * @property Thematique $resource
 */
final class ThematiqueCardResource extends JsonResource
{
    /**
     * Retire la catégorie des fiches dont la catégorie n'est pas un filtre de la liste (inactive, autre type).
     *
     * @param  Collection<int, Thematique>  $thematiques
     * @param  list<int>  $listedCategoryIds
     * @return Collection<int, Thematique>
     */
    public static function listed(Collection $thematiques, array $listedCategoryIds): Collection
    {
        return $thematiques->each(static function (Thematique $thematique) use ($listedCategoryIds): void {
            if ($thematique->category_id === null || ! in_array((int) $thematique->category_id, $listedCategoryIds, true)) {
                $thematique->setRelation('category', null);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $thematique = $this->resource;
        $category = $thematique->relationLoaded('category') ? $thematique->category : null;

        return [
            'id' => (int) $thematique->getKey(),
            'slug' => (string) $thematique->slug,
            'title' => (string) $thematique->title,
            'summary' => $thematique->summary,
            'cover_url' => $thematique->cover_url,
            'reading_minutes' => (int) $thematique->reading_minutes,
            'category' => $category === null ? null : self::category($category),
            'published_at' => $thematique->published_at?->toIso8601String(),
            'url' => route('thematiques.show', $thematique),
        ];
    }

    /**
     * @return array{slug: string, label: string, icon: string|null, color: string|null}
     */
    public static function category(Category $category): array
    {
        return [
            'slug' => (string) $category->slug,
            'label' => (string) $category->label,
            'icon' => $category->icon,
            'color' => $category->color,
        ];
    }
}
