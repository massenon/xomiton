<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Application\Shared\Referentials;
use App\Models\Category;
use App\Models\Faq;
use Illuminate\Database\Eloquent\Builder;

/**
 * Périmètre du centre d'aide `/aide` (P4-05, prototype help.php) : questions publiées, pourvues d'un slug, rangées dans
 * une catégorie active de type `faq` (compte, signalement, soutien, communauté : x_help_categories()). Les FAQ des
 * fiches thématiques (catégories `thematique`, ThematiqueFaqSeeder) et les questions sans catégorie n'y figurent pas
 * (Q58). Même périmètre pour la liste, la fiche, le vote et le sitemap.
 *
 * @phpstan-type HelpCategory array{id: int, kind: string, slug: string, label: string, icon: string|null, color: string|null, position: int}
 */
final class HelpCenter
{
    public function __construct(private readonly Referentials $referentials) {}

    /**
     * Catégories actives `faq` du référentiel en cache, par position puis libellé.
     *
     * @return list<HelpCategory>
     */
    public function categories(): array
    {
        $categories = $this->referentials->categories(Category::KIND_FAQ);
        usort($categories, static fn (array $a, array $b): int => [$a['position'], $a['label']] <=> [$b['position'], $b['label']]);

        return $categories;
    }

    /**
     * Questions du centre d'aide, dans l'ordre `position` puis `id`.
     *
     * @return Builder<Faq>
     */
    public function faqs(): Builder
    {
        return Faq::query()
            ->published()
            ->whereNotNull('slug')
            ->whereIn('category_id', array_column($this->categories(), 'id'))
            ->ordered();
    }
}
