<?php

declare(strict_types=1);

namespace App\Application\Content\Results;

use App\Models\Faq;
use Illuminate\Support\Collection;

/**
 * Résultat de ShowHelpArticle (P4-05, prototype help-details.php) : la question, sa catégorie, les repères « À savoir »
 * de la catégorie (lang/fr/pages/aide.php) et les autres questions proposées en colonne.
 *
 * @phpstan-type CategoryRow array{id: int, kind: string, slug: string, label: string, icon: string|null, color: string|null, position: int}
 */
final readonly class HelpArticle
{
    /**
     * @param  CategoryRow  $category
     * @param  list<string>  $know
     * @param  Collection<int, Faq>  $related
     */
    public function __construct(
        public Faq $faq,
        public array $category,
        public array $know,
        public Collection $related,
    ) {}
}
