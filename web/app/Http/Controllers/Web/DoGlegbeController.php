<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Content\Queries\DoGlegbePage;
use App\Application\Seo\StructuredData;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Page `/do-glegbe` (P4-04, prototype do-glegbe.php) : publique et indexable, gabarit public pour tout le monde
 * (Q18 a), vous. Contenu éditorial du prototype (lang/fr/pages/do_glegbe.php) ; fiches et quiz assemblés par
 * DoGlegbePage (section des fiches omise si la catégorie est inactive ou vide) ; quiz vrai / faux dans le navigateur
 * (resources/js/public/do-glegbe.js), corrigé lisible sans JavaScript.
 * Liens vers les ateliers, groupes et forums : routes membres (le middleware `auth` renvoie vers la connexion).
 */
final class DoGlegbeController extends Controller
{
    public function __invoke(DoGlegbePage $page): View
    {
        $content = $page();
        $canonical = route('do-glegbe');

        return view('public.do-glegbe.index', [
            'sheets' => $content->sheets,
            'sheetsUrl' => route('thematiques.index', $content->sheetsCategory !== null ? ['cat' => $content->sheetsCategory] : []),
            'sheetsTotal' => $content->sheetsTotal,
            'quiz' => $content->quiz,
            'seo' => [
                'title' => __('seo.pages.do_glegbe.title'),
                'description' => __('seo.pages.do_glegbe.description'),
                'type' => 'article',
                'image' => 'doglegbexo.png',
                'imageAlt' => __('pages/do_glegbe.hero.logo_alt'),
                'breadcrumb' => [[__('pages/do_glegbe.title'), $canonical]],
                'schema' => [StructuredData::article($canonical, [
                    'headline' => (string) __('pages/do_glegbe.headline'),
                    'description' => (string) __('pages/do_glegbe.schema_description'),
                    'section' => (string) __('pages/do_glegbe.schema_section'),
                    'image' => asset('assets/images/xomiton/doglegbexo.png'),
                ])],
            ],
        ]);
    }
}
