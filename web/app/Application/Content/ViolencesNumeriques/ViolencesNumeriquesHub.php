<?php

declare(strict_types=1);

namespace App\Application\Content\ViolencesNumeriques;

use App\Enums\ContentStatus;
use App\Models\Category;
use App\Models\CccTool;
use App\Models\Thematique;
use Illuminate\Support\Carbon;

/**
 * Lectures publiques des violences numériques (P19-03, P19-04 ; ADR-29 : lectures Eloquent directes).
 *
 *   - carrefour : catégories `violence_numerique` actives (ordre de l'administration) et leurs sujets (fiches non
 *     supprimées, ordre de création = ordre des fichiers du pilote) ; un sujet publié porte son lien et sa BD publique,
 *     un sujet non publié est « à venir » (aucun lien : jamais de 404) ;
 *   - BD publiques : publiées **et** `is_public`, de type `bd`, avec leur fiche si elle est publiée ;
 *   - fiche ↔ BD : la BD publique d'une fiche, et la catégorie du carrefour d'une fiche.
 */
final class ViolencesNumeriquesHub
{
    /**
     * @return list<array{slug: string, label: string, icon: string|null, topics: list<array{slug: string, title: string, summary: string, published: bool, comic: string|null}>}>
     */
    public function categories(): array
    {
        $categories = Category::query()->ofKind(Category::KIND_DIGITAL_VIOLENCE)->active()->ordered()->get(['id', 'slug', 'label', 'icon']);

        if ($categories->isEmpty()) {
            return [];
        }

        $topics = Thematique::query()
            ->whereIn('category_id', $categories->modelKeys())
            ->orderBy('id')
            ->get(['id', 'category_id', 'slug', 'title', 'summary', 'status', 'published_at'])
            ->groupBy('category_id');
        $comics = CccTool::query()->publicComics()->whereNotNull('thematique_id')->ordered()->get(['key', 'thematique_id'])
            ->unique('thematique_id')->pluck('key', 'thematique_id');

        return $categories->map(fn (Category $category): array => [
            'slug' => (string) $category->slug,
            'label' => (string) $category->label,
            'icon' => $category->icon === null ? null : preg_replace('/^bi-/', '', (string) $category->icon),
            'topics' => ($topics[$category->getKey()] ?? collect())->map(function (Thematique $thematique) use ($comics): array {
                $published = $this->isPublished($thematique);

                return [
                    'slug' => (string) $thematique->slug,
                    'title' => (string) $thematique->title,
                    'summary' => (string) $thematique->summary,
                    'published' => $published,
                    'comic' => $published ? ($comics[$thematique->getKey()] ?? null) : null,
                ];
            })->values()->all(),
        ])->values()->all();
    }

    /**
     * BD publiques, dans l'ordre de `/ccc`.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, CccTool>
     */
    public function comics(): \Illuminate\Database\Eloquent\Collection
    {
        return CccTool::query()->publicComics()->ordered()->with('thematique')->get();
    }

    /** BD publique par sa clé, sinon null (masquée, non publique, inconnue ou d'un autre type). */
    public function comic(string $key): ?CccTool
    {
        return CccTool::query()->publicComics()->where('key', $key)->with('thematique.category')->first();
    }

    /** Fiche publiée liée à une BD, sinon null. */
    public function ficheOf(CccTool $tool): ?Thematique
    {
        $thematique = $tool->thematique;

        return $thematique !== null && ! $thematique->trashed() && $this->isPublished($thematique) ? $thematique : null;
    }

    /** BD publique d'une fiche, sinon null. */
    public function comicOf(Thematique $thematique): ?CccTool
    {
        return CccTool::query()->publicComics()->where('thematique_id', $thematique->getKey())->ordered()->first();
    }

    public static function isDigitalViolence(?Category $category): bool
    {
        return $category !== null && (string) $category->kind === Category::KIND_DIGITAL_VIOLENCE;
    }

    private function isPublished(Thematique $thematique): bool
    {
        $published = $thematique->getRawOriginal('published_at');

        return ContentStatus::tryFrom((string) $thematique->getRawOriginal('status')) === ContentStatus::Published
            && $published !== null
            && Carbon::parse((string) $published)->lessThanOrEqualTo(now());
    }
}
