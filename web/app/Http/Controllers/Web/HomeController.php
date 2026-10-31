<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Thematique;
use App\Support\PublicContentCache;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * Page d'accueil `/` (P4-01) : les 11 sections de prototype_social/index.php, dont deux lues en base — les six premières
 * thématiques publiées (section « Ressources ») et les 5–6 FAQ marquées « accueil » (Q16), publiées aussi en JSON-LD
 * `FAQPage`. Lectures Eloquent directes (ADR-29) : trois requêtes indexées (fiches, catégories, FAQ), gardées dans le cache de
 * données App\Support\PublicContentCache et vidées à chaque modification de contenu (PublicContentObserver).
 */
final class HomeController extends Controller
{
    /** Fiches thématiques affichées (prototype : array_slice(x_thematiques(), 0, 6)). */
    public const THEMATIQUES_LIMIT = 6;

    /** Questions de la FAQ d'accueil (Q16 : 5–6, lien vers /aide pour le reste). */
    public const FAQ_LIMIT = 6;

    public function __invoke(): View
    {
        $faqs = $this->faqs();

        return view('public.home', [
            'thematiques' => $this->thematiques(),
            'faqs' => $faqs,
            'faqItems' => $this->faqItems($faqs),
            'faqSchema' => $this->faqSchema($faqs),
        ]);
    }

    /**
     * Une requête pour les fiches, une pour leurs catégories (eager loading, aucun N+1), gardées dans le cache de données
     * des pages publiques jusqu'à la prochaine modification de contenu (PublicContentObserver) ou au plus 10 minutes (une publication
     * programmée apparaît donc au plus 10 minutes après son heure).
     *
     * @return Collection<int, Thematique>
     */
    private function thematiques(): Collection
    {
        return PublicContentCache::remember('home.thematiques', fn (): Collection => Thematique::query()
            ->published()
            ->with('category:id,label,icon,color')
            ->oldest('id')
            ->limit(self::THEMATIQUES_LIMIT)
            ->get(['id', 'slug', 'title', 'summary', 'cover_path', 'image_path', 'category_id']));
    }

    /** @return Collection<int, Faq> */
    private function faqs(): Collection
    {
        return PublicContentCache::remember('home.faqs', fn (): Collection => Faq::query()
            ->onHome()
            ->ordered()
            ->limit(self::FAQ_LIMIT)
            ->get(['id', 'question', 'answer']));
    }

    /**
     * Éléments de <x-accordion> : la réponse est du texte brut (DATABASE_DICTIONARY.md), échappée ici car le composant
     * l'insère telle quelle.
     *
     * @param  Collection<int, Faq>  $faqs
     * @return list<array{q: string, a: string}>
     */
    private function faqItems(Collection $faqs): array
    {
        return $faqs->map(fn (Faq $faq): array => [
            'q' => (string) $faq->question,
            'a' => '<p class="mb-0">'.e((string) $faq->answer).'</p>',
        ])->values()->all();
    }

    /**
     * JSON-LD `FAQPage` (FRONT_END_SEO_PERFECT_AUDIT.md) : mêmes questions et réponses que l'accordéon, ou rien.
     *
     * @param  Collection<int, Faq>  $faqs
     * @return array<string, mixed>|null
     */
    private function faqSchema(Collection $faqs): ?array
    {
        if ($faqs->isEmpty()) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            '@id' => url('/').'#faq',
            'inLanguage' => __('seo.in_language'),
            'mainEntity' => $faqs->map(fn (Faq $faq): array => [
                '@type' => 'Question',
                'name' => (string) $faq->question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => (string) $faq->answer],
            ])->values()->all(),
        ];
    }
}
