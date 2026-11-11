<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Content\Queries\ViolencesPage;
use App\Application\Seo\StructuredData;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Page `/violences` (P4-04, prototype violences.php) : publique et indexable (Q19 a), gabarit public pour tout le monde
 * (Q18 a), vous. Contenu assemblé par ViolencesPage (formes du référentiel, signes, cycle, questions, seuils).
 * Auto-évaluation confidentielle : questions rendues ici, résultat calculé dans le navigateur par
 * resources/js/public/violences.js (aucune requête réseau, aucun stockage, aucun formulaire envoyable).
 * SEO : Article (og:type article) et fil d'Ariane Accueil › Comprendre les violences, visible dans la page.
 */
final class ViolencesController extends Controller
{
    public function __invoke(ViolencesPage $page): View
    {
        $content = $page();
        $total = count($content->questions);
        $canonical = route('violences');

        return view('public.violences.index', [
            'forms' => $content->forms,
            'cycle' => $content->cycle,
            'questions' => $content->questions,
            'check' => [
                'total' => $total,
                'lowMax' => $content->lowMax,
                'mediumMax' => $content->mediumMax,
                'texts' => [
                    'incomplete' => __('pages/violences.check.incomplete', ['count' => $total]),
                    'resetDone' => __('pages/violences.check.reset_done'),
                ],
            ],
            'seo' => [
                'title' => __('seo.pages.violences.title'),
                'description' => __('seo.pages.violences.description'),
                'type' => 'article',
                'image' => 'hero-13.jpg',
                'imageAlt' => __('pages/violences.og_alt'),
                'breadcrumb' => [[__('pages/violences.title'), $canonical]],
                'schema' => [StructuredData::article($canonical, [
                    'headline' => (string) __('pages/violences.title'),
                    'description' => (string) __('seo.pages.violences.description'),
                    'section' => (string) __('pages/violences.schema_section'),
                    'image' => asset('assets/images/xomiton/hero-13.jpg'),
                ])],
            ],
        ]);
    }
}
