<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Content\OfflineLibrary;
use App\Application\Content\Queries\ShowResource;
use App\Application\Content\RecordResourceView;
use App\Application\Content\Results\ResourceSheet;
use App\Application\Seo\MetaText;
use App\Application\Seo\ResourceStructuredData;
use App\Enums\ResourceKind;
use App\Http\Controllers\Controller;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Fiche publique `/ressources/{slug}` (P4-03 ; sans prototype : mise en page de thematique.php, cible de l'ancienne
 * redirection ressource.php). Publique et indexable (Q19 a), gabarit public pour tout le monde (Q18). Ressource non
 * publiée ou inconnue → 404 (ShowResource) ; actualité (`article`) → 301 vers `/actualites/{slug}` (Q1). Fil d'Ariane
 * visible et BreadcrumbList de même source ; JSON-LD selon le type (ResourceStructuredData) à côté du WebPage.
 */
final class RessourcesShowController extends Controller
{
    /** Types dont la page est un contenu à lire : og:type article. */
    private const READABLE = [ResourceKind::Sheet, ResourceKind::Guide, ResourceKind::Pdf];

    public function __invoke(Request $request, string $slug, ShowResource $show, RecordResourceView $recordView): View|RedirectResponse
    {
        $sheet = $show($slug);
        $resource = $sheet->resource;

        if ($resource->kind === ResourceKind::Article) {
            return redirect()->to(route('actualites.show', $resource->slug), 301);
        }

        $recordView($resource, $request);

        $fmt = LocaleFormatter::make();
        $crumbs = array_values(array_filter([
            [__('pages/ressources.title'), route('ressources.index')],
            $sheet->category ? [$sheet->category->label, route('ressources.index', ['cat' => $sheet->category->slug])] : null,
            [$resource->title, route('ressources.show', $resource->slug)],
        ]));
        $downloadUrl = $resource->isDownloadable() ? route('ressources.download', $resource->slug) : null;
        $streamUrl = $resource->isPlayable() ? route('ressources.stream', $resource->slug) : null;
        $fileSize = $downloadUrl !== null && $resource->file_size ? $fmt->fileSize((int) $resource->file_size) : null;
        $offlineEnabled = OfflineLibrary::enabled();

        return view('public.ressources.show', [
            'resource' => $resource,
            'category' => $sheet->category,
            'thematique' => $sheet->thematique,
            'related' => $sheet->related,
            'breadcrumb' => [
                ['label' => __('seo.breadcrumb_home'), 'url' => route('home')],
                ...array_map(static fn (array $crumb): array => ['label' => $crumb[0], 'url' => $crumb[1]], $crumbs),
            ],
            'downloadUrl' => $downloadUrl,
            'streamUrl' => $streamUrl,
            'offlineFile' => OfflineLibrary::fileUrl($resource),
            'fileSize' => $fileSize,
            'showLegalNotice' => in_array($sheet->category?->slug, (array) config('xomiton.content.legal_notice_categories', []), true),
            'offlineEnabled' => $offlineEnabled,
            'offlineOptions' => $offlineEnabled ? OfflineLibrary::options() : null,
            'fmt' => $fmt,
            'seo' => $this->seo($sheet, $crumbs, $downloadUrl, $streamUrl, $fileSize, $fmt),
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $crumbs
     * @return array<string, mixed> props de `<x-seo>`
     */
    private function seo(ResourceSheet $sheet, array $crumbs, ?string $downloadUrl, ?string $streamUrl, ?string $fileSize, LocaleFormatter $fmt): array
    {
        $resource = $sheet->resource;
        $canonical = route('ressources.show', $resource->slug);
        $title = MetaText::title($resource->meta_title ?: (string) $resource->title);
        $description = $resource->meta_description
            ?: MetaText::description((string) $resource->summary, $resource->body, (string) __('pages/ressources.meta_description_filler'));
        $modified = $resource->updated_at ? $fmt->isoDate($resource->updated_at) : null;
        $schema = ResourceStructuredData::for($resource, $canonical, [
            'description' => (string) $description,
            'image' => $resource->cover_url,
            'published' => $resource->published_at ? $fmt->isoDate($resource->published_at) : null,
            'modified' => $modified,
            'downloadUrl' => $downloadUrl,
            'streamUrl' => $streamUrl,
            'fileSize' => $fileSize,
        ]);

        return [
            'title' => $title,
            'description' => $description,
            'type' => in_array($resource->kind, self::READABLE, true) ? 'article' : 'website',
            'image' => $resource->cover_url,
            'imageAlt' => __('pages/ressources.cover_alt', ['title' => $resource->title]),
            'breadcrumb' => $crumbs,
            'modified' => $modified,
            'schema' => $schema === null ? [] : [$schema],
        ];
    }
}
