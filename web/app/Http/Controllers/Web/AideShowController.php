<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Content\Queries\ShowHelpArticle;
use App\Application\Content\Results\HelpArticle;
use App\Application\Seo\MetaText;
use App\Http\Controllers\Controller;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Article du centre d'aide `/aide/{slug}` (P4-05, prototype help-details.php) : public et indexable, gabarit public
 * (Q18 a), vous. Question hors du centre d'aide ou inconnue → 404 (ShowHelpArticle). Fil d'Ariane Accueil › Centre
 * d'aide › Catégorie › question (le prototype finissait par « Article {id} »), même source que le BreadcrumbList.
 * Pas de FAQPage ici : la question est balisée une seule fois sur le site (accueil ou /aide, AideIndexController).
 * Vote « Cet article vous a-t-il aidé·e ? » : formulaire POST (AideFeedbackController), remerciement de la session flash.
 */
final class AideShowController extends Controller
{
    public function __invoke(Request $request, string $slug, ShowHelpArticle $show): View
    {
        $article = $show($slug);
        $faq = $article->faq;
        $crumbs = [
            [__('pages/aide.title'), route('aide.index')],
            [$article->category['label'], route('aide.index', ['cat' => $article->category['slug']])],
            [(string) $faq->question, route('aide.show', $faq->slug)],
        ];
        $flash = $request->session()->get('aide_feedback');

        return view('public.aide.show', [
            'faq' => $faq,
            'category' => $article->category,
            'know' => $article->know,
            'related' => $article->related,
            'breadcrumb' => [
                ['label' => __('seo.breadcrumb_home'), 'url' => route('home')],
                ...array_map(static fn (array $crumb): array => ['label' => $crumb[0], 'url' => $crumb[1]], $crumbs),
            ],
            // Remerciement après un vote sans JavaScript (redirection de AideFeedbackController), pour cette question seulement.
            'feedbackThanks' => is_array($flash) && ($flash['slug'] ?? null) === $faq->slug
                ? (string) __(($flash['helpful'] ?? false) ? 'pages/aide.feedback.thanks_yes' : 'pages/aide.feedback.thanks_no')
                : null,
            'seo' => $this->seo($article, $crumbs),
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $crumbs
     * @return array<string, mixed> props de `<x-seo>`
     */
    private function seo(HelpArticle $article, array $crumbs): array
    {
        $faq = $article->faq;
        $know = implode('', array_map(static fn (string $item): string => '<p>'.e($item).'</p>', $article->know));

        return [
            'title' => MetaText::title((string) $faq->question),
            'description' => MetaText::description((string) $faq->answer, $know, (string) __('pages/aide.meta_description_filler')),
            'imageAlt' => __('pages/aide.og_alt'),
            'modified' => $faq->updated_at ? LocaleFormatter::make()->isoDate($faq->updated_at) : null,
            'breadcrumb' => $crumbs,
        ];
    }
}
