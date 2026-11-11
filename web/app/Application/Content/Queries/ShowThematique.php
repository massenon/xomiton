<?php

declare(strict_types=1);

namespace App\Application\Content\Queries;

use App\Application\Content\Results\ThematiqueSheet;
use App\Application\Shared\Referentials;
use App\Enums\DirectoryRole;
use App\Enums\ResourceKind;
use App\Models\Category;
use App\Models\DirectoryContact;
use App\Models\Faq;
use App\Models\Resource;
use App\Models\Thematique;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * Fiche thématique publique (P4-02, prototype thematique.php) et ses blocs liés. Lecture seule (ADR-29).
 *
 *   - fiche : publiée (statut, date passée), non supprimée, sinon ModelNotFoundException (404) — pas de liaison implicite,
 *     qui servirait aussi brouillons et fiches programmées ;
 *   - catégorie du fil d'Ariane (visible et BreadcrumbList) : seulement si elle figure parmi les filtres de la liste
 *     (Referentials : active, de type `thematique`) ; sinon la fiche reste servie et listée dans « Toutes », sans lien
 *     vers un `?cat=` qui répondrait 404 ;
 *   - contacts : rôles `related_contact_roles` de la fiche, contacts **actifs et publics seulement** (Q19), dans l'ordre
 *     des rôles puis point focal national d'abord ; ni notes ni e-mail ne sortent ;
 *   - fiches liées : `related_ids` dans l'ordre, complétées par la même catégorie puis les autres (prototype l.11-15),
 *     colonnes d'une carte (résumé compris) et catégorie chargée seulement si elle est un filtre de la liste ;
 *   - ressource principale : décision par défaut (plan §7) — la ressource publiée la plus récente rattachée à la fiche,
 *     hors actualités (`kind = article`, Q1), faute de colonne dédiée ;
 *   - FAQ : décision par défaut (plan §7) — questions publiées de la catégorie de la fiche, 3 à 5, sinon aucune.
 */
final class ShowThematique
{
    public function __construct(private readonly Referentials $referentials) {}

    /** @var list<string> */
    private const RELATED_COLUMNS = ['id', 'category_id', 'slug', 'title', 'summary', 'reading_minutes', 'cover_path', 'image_path', 'published_at'];

    /** Catégorie des fiches liées (carte de l'API mobile, ThematiqueCardResource), comme ListThematiques. */
    private const RELATED_CATEGORY = 'category:id,slug,label,icon,color';

    /** @throws ModelNotFoundException<Thematique> */
    public function __invoke(string $slug): ThematiqueSheet
    {
        $thematique = Thematique::query()->published()->where('slug', $slug)->with('category')->firstOrFail();

        return new ThematiqueSheet(
            $thematique,
            $this->listedCategory($thematique),
            $this->contacts($thematique),
            $this->related($thematique),
            $this->primaryResource($thematique),
            $this->faqs($thematique),
        );
    }

    private function listedCategory(Thematique $thematique): ?Category
    {
        $category = $thematique->category;

        return $category !== null && in_array((int) $category->getKey(), $this->listedCategoryIds(), true) ? $category : null;
    }

    /** @return list<int> catégories actives de type `thematique` (filtres de la liste publique) */
    private function listedCategoryIds(): array
    {
        return array_map(static fn (array $category): int => (int) $category['id'], $this->referentials->categories(Category::KIND_THEMATIQUE));
    }

    /** @return Collection<int, DirectoryContact> */
    private function contacts(Thematique $thematique): Collection
    {
        $roles = collect($thematique->related_contact_roles ?? [])
            ->map(static fn (mixed $role): ?DirectoryRole => DirectoryRole::tryFrom((string) $role))
            ->filter()
            ->unique()
            ->values();

        if ($roles->isEmpty()) {
            return collect();
        }

        $order = 'case role '.$roles->map(static fn (): string => 'when ? then ?')->implode(' ').' end';
        $bindings = $roles->flatMap(static fn (DirectoryRole $role, int $index): array => [$role->value, $index])->all();

        return DirectoryContact::query()
            ->active()
            ->public()
            ->whereIn('role', $roles->map->value->all())
            ->with('zone:id,name')
            ->orderByRaw($order, $bindings)
            ->orderByDesc('is_national_focal')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(max(0, (int) config('xomiton.content.contacts_max', 4)))
            ->get(['id', 'name', 'role', 'org', 'phone', 'whatsapp', 'zone_id', 'availability', 'is_national_focal']);
    }

    /** @return Collection<int, Thematique> */
    private function related(Thematique $thematique): Collection
    {
        $wanted = max(0, (int) config('xomiton.content.related_count', 3));
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $thematique->related_ids ?? []),
            static fn (int $id): bool => $id > 0 && $id !== (int) $thematique->getKey(),
        )));

        $related = $ids === [] ? collect() : Thematique::query()->published()->whereIn('id', $ids)->with(self::RELATED_CATEGORY)->get(self::RELATED_COLUMNS)
            ->sortBy(static fn (Thematique $item): int|false => array_search((int) $item->getKey(), $ids, true))
            ->values();
        $related = $related->take($wanted);

        // Complément : même catégorie d'abord, puis les autres, dans l'ordre de la liste publique.
        foreach ([true, false] as $sameCategory) {
            $missing = $wanted - $related->count();
            if ($missing <= 0) {
                break;
            }

            $related = $related->concat(Thematique::query()->published()
                ->whereKeyNot([(int) $thematique->getKey(), ...$related->map(static fn (Thematique $item): int => (int) $item->getKey())->all()])
                ->where('category_id', $sameCategory ? '=' : '!=', $thematique->category_id)
                ->orderByDesc('published_at')
                ->orderBy('id')
                ->limit($missing)
                ->with(self::RELATED_CATEGORY)
                ->get(self::RELATED_COLUMNS));
        }

        $listed = $this->listedCategoryIds();

        // Catégorie inactive ou d'un autre type : retirée, jamais un slug qui répondrait 404 à `?categorie=`.
        return $related->values()->each(static function (Thematique $item) use ($listed): void {
            if ($item->category_id === null || ! in_array((int) $item->category_id, $listed, true)) {
                $item->setRelation('category', null);
            }
        });
    }

    private function primaryResource(Thematique $thematique): ?Resource
    {
        return $thematique->resources()
            ->published()
            ->whereNot('kind', ResourceKind::Article)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            // file_path, external_url, image_path : carte de l'API mobile (P6-08 : téléchargeable, lien externe, couverture).
            ->first(['id', 'thematique_id', 'slug', 'title', 'kind', 'summary', 'file_path', 'file_size', 'external_url', 'image_path', 'duration_minutes', 'is_offline_available', 'published_at']);
    }

    /** @return Collection<int, Faq> */
    private function faqs(Thematique $thematique): Collection
    {
        $faqs = Faq::query()
            ->published()
            ->where('category_id', $thematique->category_id)
            ->ordered()
            ->limit(max(0, (int) config('xomiton.content.faq_max', 5)))
            ->get(['id', 'question', 'answer']);

        return $faqs->count() < (int) config('xomiton.content.faq_min', 3) ? collect() : $faqs;
    }
}
