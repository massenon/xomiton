<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Content\Queries\ShowThematique;
use App\Application\Content\RecordThematiqueView;
use App\Application\Content\Results\ThematiqueSheet;
use App\Application\Seo\MetaText;
use App\Application\Seo\StructuredData;
use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Fiche publique `/thematiques/{slug}` (P4-02, prototype thematique.php) : publique et indexable (Q19 a), gabarit public
 * pour tout le monde (Q18). Fiche non publiée ou inconnue → 404 (ShowThematique). JSON-LD : Article + FAQPage (si 3 à 5
 * questions) à côté du WebPage et du BreadcrumbList de `<x-seo>` ; fil d'Ariane visible et JSON-LD de même source. Le
 * maillon catégorie n'apparaît que si la catégorie est un filtre de la liste (ShowThematique) : jamais de lien vers un 404.
 */
final class ThematiquesShowController extends Controller
{
    /** Catégories dont la fiche propose l'assistant aux membres (prototype x_thematique_content() l.132). */
    private const ASSISTANT_CATEGORIES = ['mental', 'esc'];

    public function __invoke(Request $request, string $slug, ShowThematique $show, RecordThematiqueView $recordView): View
    {
        $sheet = $show($slug);
        $recordView($sheet->thematique, $request);

        $thematique = $sheet->thematique;
        $category = $thematique->category;
        $listUrl = route('thematiques.index');
        $crumbs = array_values(array_filter([
            [__('pages/thematiques.title'), $listUrl],
            $sheet->category ? [$sheet->category->label, route('thematiques.index', ['cat' => $sheet->category->slug])] : null,
            [$thematique->title, route('thematiques.show', $thematique)],
        ]));

        return view('public.thematiques.show', [
            'thematique' => $thematique,
            'category' => $category,
            'breadcrumb' => [
                ['label' => __('seo.breadcrumb_home'), 'url' => route('home')],
                ...array_map(static fn (array $crumb): array => ['label' => $crumb[0], 'url' => $crumb[1]], $crumbs),
            ],
            'sections' => array_values(array_map(static fn (array $section): array => [
                'q' => (string) ($section['title'] ?? ''),
                'a' => (string) ($section['body'] ?? ''),
            ], array_filter($thematique->sections ?? [], 'is_array'))),
            'contacts' => $sheet->contacts,
            'related' => $sheet->related,
            'primaryResource' => $sheet->primaryResource,
            'resourceSize' => $sheet->primaryResource?->file_size ? LocaleFormatter::make()->fileSize((int) $sheet->primaryResource->file_size) : null,
            'faqItems' => $sheet->faqs->map(static fn (Faq $faq): array => [
                'q' => (string) $faq->question,
                // Réponse en texte brut (DATABASE_DICTIONARY.md), échappée ici : <x-accordion> l'insère telle quelle.
                'a' => '<p class="mb-0">'.e((string) $faq->answer).'</p>',
            ])->values()->all(),
            'showAssistant' => in_array($category?->slug, self::ASSISTANT_CATEGORIES, true),
            'seo' => $this->seo($sheet, $crumbs),
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $crumbs
     * @return array<string, mixed> props de `<x-seo>`
     */
    private function seo(ThematiqueSheet $sheet, array $crumbs): array
    {
        $thematique = $sheet->thematique;
        $canonical = route('thematiques.show', $thematique);
        $formatter = LocaleFormatter::make();
        $title = MetaText::title($thematique->meta_title ?: (string) $thematique->title);
        $description = $thematique->meta_description
            ?: MetaText::description((string) $thematique->summary, $thematique->body, (string) __('pages/thematiques.meta_description_filler'));
        $modified = $thematique->updated_at ? $formatter->isoDate($thematique->updated_at) : null;

        return [
            'title' => $title,
            'description' => $description,
            'type' => 'article',
            'image' => $thematique->cover_url,
            'imageAlt' => __('pages/thematiques.cover_alt', ['title' => $thematique->title]),
            'breadcrumb' => $crumbs,
            'modified' => $modified,
            'schema' => array_values(array_filter([
                StructuredData::article($canonical, [
                    'headline' => (string) $thematique->title,
                    'description' => (string) $description,
                    'section' => $thematique->category?->label,
                    'minutes' => (int) $thematique->reading_minutes,
                    'published' => $thematique->published_at ? $formatter->isoDate($thematique->published_at) : null,
                    'modified' => $modified,
                    'image' => $thematique->cover_url,
                ]),
                StructuredData::faqPage($canonical, $sheet->faqs->map(static fn (Faq $faq): array => [
                    'question' => (string) $faq->question,
                    'answer' => (string) $faq->answer,
                ])),
            ])),
        ];
    }
}
