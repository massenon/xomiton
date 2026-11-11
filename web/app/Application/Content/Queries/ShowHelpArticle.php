<?php

declare(strict_types=1);

namespace App\Application\Content\Queries;

use App\Application\Content\HelpCenter;
use App\Application\Content\Results\HelpArticle;
use App\Domain\Auth\PasswordStrength;
use App\Models\Faq;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Article du centre d'aide `/aide/{slug}` (P4-05, prototype help-details.php) : question du périmètre HelpCenter
 * (sinon ModelNotFoundException, 404 ; pas de liaison implicite), sa catégorie, les repères « À savoir » de la catégorie
 * (`$more` du prototype, lang/fr/pages/aide.php `know.{slug}` : rien pour une catégorie ajoutée sans texte) et les autres
 * questions de la colonne (prototype : les sept premières ; ici la même catégorie d'abord, puis les autres, dans l'ordre).
 */
final class ShowHelpArticle
{
    /** Nombre d'« Autres questions » proposées. */
    public const RELATED_COUNT = 6;

    public function __construct(private readonly HelpCenter $helpCenter) {}

    /** @throws ModelNotFoundException<Faq> */
    public function __invoke(string $slug): HelpArticle
    {
        $faq = $this->helpCenter->faqs()->where('slug', $slug)->firstOrFail();
        $category = collect($this->helpCenter->categories())->firstWhere('id', (int) $faq->category_id);

        $others = $this->helpCenter->faqs()->whereKeyNot($faq->getKey())->get(['id', 'category_id', 'slug', 'question']);
        $related = $others->where('category_id', $faq->category_id)
            ->concat($others->where('category_id', '!=', $faq->category_id))
            ->take(self::RELATED_COUNT)
            ->values();

        return new HelpArticle(faq: $faq, category: $category, know: self::know($category['slug']), related: $related);
    }

    /** @return list<string> */
    private static function know(string $categorySlug): array
    {
        $key = 'pages/aide.know.'.$categorySlug;
        $items = __($key);

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            static fn (int|string $index): string => (string) __($key.'.'.$index, ['min' => PasswordStrength::MIN_LENGTH]),
            array_keys($items),
        ));
    }
}
