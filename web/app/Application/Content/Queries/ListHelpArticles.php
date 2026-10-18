<?php

declare(strict_types=1);

namespace App\Application\Content\Queries;

use App\Application\Content\HelpCenter;
use App\Application\Content\Results\HelpCenterListing;
use App\Application\Shared\KnowledgeBaseAnswerer;
use App\Models\Faq;
use Illuminate\Support\Str;

/**
 * Centre d'aide `/aide` (P4-05, prototype help.php) : questions du périmètre HelpCenter, filtrées par catégorie (`cat`,
 * slug) et par recherche (`q`). Les catégories affichées sont celles qui ont au moins une question (règle Q60) ; leur
 * nombre ignore la recherche, comme le prototype. Catégorie inconnue, inactive ou vide → null (404 par l'appelant).
 *
 * Recherche dans le code plutôt qu'en SQL : le centre d'aide compte quelques dizaines de questions, et la comparaison
 * se fait sans accents ni casse (« donnees » trouve « Mes données… », ce que LIKE ne garantit ni sous SQLite ni selon la
 * collation MySQL) ; chaque mot significatif doit figurer dans la question ou la réponse. Mots significatifs = ceux de
 * l'assistant (KnowledgeBaseAnswerer::tokens : trois caractères au moins, hors mots vides du français) : « comment
 * signaler un abus » cherche « signaler » et « abus ». Une recherche sans mot significatif (« le », « a b ») affiche
 * toutes les questions, sans compte annoncé (`searched` faux). Lecture seule (ADR-29).
 */
final class ListHelpArticles
{
    public function __construct(private readonly HelpCenter $helpCenter) {}

    public function __invoke(?string $categorySlug, string $search): ?HelpCenterListing
    {
        $faqs = $this->helpCenter->faqs()->get(['id', 'category_id', 'slug', 'question', 'answer', 'position', 'show_on_home', 'updated_at']);
        $counts = $faqs->countBy('category_id')->all();

        $categories = [];
        foreach ($this->helpCenter->categories() as $row) {
            if (($counts[$row['id']] ?? 0) > 0) {
                $categories[] = [...$row, 'count' => (int) $counts[$row['id']]];
            }
        }

        $category = null;
        if ($categorySlug !== null) {
            $category = collect($categories)->firstWhere('slug', $categorySlug);

            if ($category === null) {
                return null;
            }
        }

        $terms = self::terms($search);
        $shown = $faqs
            ->when($category !== null, static fn ($list) => $list->where('category_id', $category['id']))
            ->filter(static fn (Faq $faq): bool => self::matches($faq, $terms))
            ->values();

        return new HelpCenterListing(
            categories: $categories,
            category: $category,
            search: $search,
            searched: $terms !== [],
            faqs: $shown,
            totalAll: $faqs->count(),
            updatedAt: $faqs->max('updated_at')?->toDateString(),
        );
    }

    /** Forme comparable : minuscules, sans accents (« É » → « e », « ’ » → « ' »). */
    public static function normalize(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }

    /**
     * Mots significatifs recherchés, sous forme comparable (mêmes règles que l'assistant public).
     *
     * @return list<string>
     */
    private static function terms(string $search): array
    {
        return KnowledgeBaseAnswerer::tokens(KnowledgeBaseAnswerer::normalize($search));
    }

    /** @param  list<string>  $terms */
    private static function matches(Faq $faq, array $terms): bool
    {
        if ($terms === []) {
            return true;
        }

        $haystack = self::normalize($faq->question.' '.$faq->answer);

        foreach ($terms as $term) {
            if (! str_contains($haystack, $term)) {
                return false;
            }
        }

        return true;
    }
}
