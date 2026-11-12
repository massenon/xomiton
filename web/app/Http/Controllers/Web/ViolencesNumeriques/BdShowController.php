<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\ViolencesNumeriques;

use App\Application\Content\ViolencesNumeriques\Discretion;
use App\Application\Content\ViolencesNumeriques\ViolencesNumeriquesHub;
use App\Application\Seo\MetaText;
use App\Application\Seo\StructuredData;
use App\Http\Controllers\Controller;
use App\Models\CccTool;
use App\Models\Thematique;
use App\Models\User;
use App\Support\LocaleFormatter;
use App\Support\MediaUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Page publique d'une BD `/bd/{key}` (P19-04, Q19, P8-12 « bd-ssr ») : BD publiée et publique, sinon 404 (masquée,
 * réservée aux membres, inconnue ou d'un autre type : réponse identique). Avertissement de contenu de la fiche liée,
 * visionneuse accessible `x-comic-viewer`, lien vers la fiche, Signaler, SOS et urgences, autres BD. Mode discret
 * (Discretion) : titre neutre et planches repliées pour une personne connectée qui l'a choisi.
 * SEO : og:image = visuel de partage 1080 × 1080 de la fiche, sinon la couverture ; JSON-LD CreativeWork + BreadcrumbList.
 */
final class BdShowController extends Controller
{
    public function __invoke(Request $request, string $key, ViolencesNumeriquesHub $hub, Discretion $discretion): View
    {
        $tool = $hub->comic($key);
        abort_if($tool === null, 404);

        $fiche = $hub->ficheOf($tool);
        $title = (string) $tool->title;
        $canonical = route('bd.show', ['key' => $tool->key]);
        $user = $request->user();
        $user = $user instanceof User ? $user : null;
        $others = $hub->comics()->reject(static fn (CccTool $other): bool => $other->key === $tool->key)->take(3)->values();

        return view('public.bd.show', [
            'tool' => $tool,
            'fiche' => $fiche,
            'pages' => array_values(array_filter((array) $tool->pages, 'is_array')),
            'warning' => $fiche?->content_warning,
            'others' => $others,
            'hideImages' => $discretion->hidesImages($user),
            'breadcrumb' => [
                ['label' => __('seo.breadcrumb_home'), 'url' => route('home')],
                ['label' => __('pages/bd.title'), 'url' => route('bd.index')],
                ['label' => $title],
            ],
            'seo' => $discretion->seo($this->seo($tool, $fiche, $canonical), $user),
        ]);
    }

    /** @return array<string, mixed> */
    private function seo(CccTool $tool, ?Thematique $fiche, string $canonical): array
    {
        $title = (string) $tool->title;
        $captions = implode(' ', array_map(static fn (mixed $page): string => is_array($page) ? (string) ($page['caption'] ?? '') : '', (array) $tool->pages));
        $description = MetaText::description((string) $tool->description, '<p>'.e($captions).'</p>', (string) __('pages/bd.meta_description_filler'));
        $cover = MediaUrl::resolve($tool->image_path, 'assets/images/xomiton/covers/og-default.jpg');
        $image = $fiche !== null && MediaUrl::exists($fiche->og_image_path) ? MediaUrl::resolve($fiche->og_image_path, $cover) : $cover;
        $modified = $tool->updated_at !== null ? LocaleFormatter::make()->isoDate($tool->updated_at) : null;
        $root = StructuredData::root();

        return [
            'title' => MetaText::title($fiche !== null ? (string) __('pages/bd.meta_title_show', ['title' => $title, 'topic' => $fiche->title]) : $title),
            'description' => $description,
            'type' => 'article',
            'image' => $image,
            'imageAlt' => __('pages/bd.cover_alt', ['title' => $title]),
            'breadcrumb' => [[__('pages/bd.title'), route('bd.index')], [$title, $canonical]],
            'modified' => $modified,
            'schema' => [array_filter([
                '@type' => 'CreativeWork',
                '@id' => $canonical.'#bd',
                'name' => $title,
                'headline' => $title,
                'description' => $description,
                'genre' => (string) __('pages/bd.title'),
                'inLanguage' => __('seo.in_language'),
                'image' => array_values(array_unique([$image, $cover])),
                'author' => ['@id' => $root.'#org'],
                'publisher' => ['@id' => $root.'#org'],
                'dateModified' => $modified,
                'about' => $fiche !== null ? ['@type' => 'Article', 'name' => (string) $fiche->title, 'url' => route('thematiques.show', ['slug' => $fiche->slug])] : null,
                'mainEntityOfPage' => ['@id' => $canonical.'#page'],
                'isAccessibleForFree' => true,
            ], static fn (mixed $value): bool => $value !== null)],
        ];
    }
}
