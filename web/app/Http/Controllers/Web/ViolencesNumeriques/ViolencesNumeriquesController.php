<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\ViolencesNumeriques;

use App\Application\Content\ViolencesNumeriques\Discretion;
use App\Application\Content\ViolencesNumeriques\ViolencesNumeriquesHub;
use App\Application\Seo\StructuredData;
use App\Http\Controllers\Controller;
use App\Models\CccTool;
use App\Models\User;
use App\Support\MediaUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Carrefour public `/violences-numeriques` (P19-03 ; décisions du client du 12/11/2026) : publique et indexable, gabarit
 * public, vous. Les 6 catégories (`violence_numerique`) et leurs 23 sujets : un sujet publié mène à sa fiche
 * (`/thematiques/{slug}`) et à sa BD publique (`/bd/{key}`) ; un sujet « à venir » est affiché sobrement, sans lien.
 * Liens vers Signaler, SOS et les urgences. SEO : CollectionPage des fiches publiées + BreadcrumbList.
 */
final class ViolencesNumeriquesController extends Controller
{
    public function __invoke(Request $request, ViolencesNumeriquesHub $hub, Discretion $discretion): View
    {
        $categories = $hub->categories();
        $comics = $hub->comics();
        $canonical = route('violences-numeriques');
        $title = (string) __('pages/violences_numeriques.title');
        $items = [];

        foreach ($categories as $category) {
            foreach ($category['topics'] as $topic) {
                if ($topic['published']) {
                    $items[] = ['url' => route('thematiques.show', ['slug' => $topic['slug']]), 'name' => $topic['title']];
                }
            }
        }

        $cover = $comics->first();
        $user = $request->user();

        return view('public.violences-numeriques.index', [
            'categories' => $categories,
            'comics' => $comics->take(4),
            'comicsTotal' => $comics->count(),
            'breadcrumb' => [['label' => __('seo.breadcrumb_home'), 'url' => route('home')], ['label' => $title]],
            'seo' => $discretion->seo([
                'title' => __('pages/violences_numeriques.meta_title'),
                'description' => __('pages/violences_numeriques.meta_description'),
                'image' => $cover instanceof CccTool ? MediaUrl::resolve($cover->image_path, 'assets/images/xomiton/covers/og-default.jpg') : 'covers/og-default.jpg',
                'imageAlt' => $cover instanceof CccTool ? __('pages/violences_numeriques.og_alt', ['title' => $cover->title]) : null,
                'breadcrumb' => [[$title, $canonical]],
                'schema' => [StructuredData::collectionPage($canonical, $title, count($items), $items)],
            ], $user instanceof User ? $user : null),
        ]);
    }
}
