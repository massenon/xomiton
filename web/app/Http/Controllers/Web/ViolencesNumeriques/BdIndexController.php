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
 * Liste publique des BD `/bd` (P19-04, Q19) : BD publiées **et** publiques seulement (`ccc_tools.is_public`, toggle de
 * `/admin/ccc`) ; bd1–bd3 restent réservées aux membres et pair·es (`/ccc`). Indexable, vous. SEO : CollectionPage.
 */
final class BdIndexController extends Controller
{
    public function __invoke(Request $request, ViolencesNumeriquesHub $hub, Discretion $discretion): View
    {
        $comics = $hub->comics();
        $canonical = route('bd.index');
        $title = (string) __('pages/bd.title');
        $first = $comics->first();
        $user = $request->user();

        return view('public.bd.index', [
            'comics' => $comics,
            'breadcrumb' => [['label' => __('seo.breadcrumb_home'), 'url' => route('home')], ['label' => $title]],
            'seo' => $discretion->seo([
                'title' => __('pages/bd.meta_title'),
                'description' => __('pages/bd.meta_description'),
                'image' => $first instanceof CccTool ? MediaUrl::resolve($first->image_path, 'assets/images/xomiton/covers/og-default.jpg') : 'covers/og-default.jpg',
                'imageAlt' => $first instanceof CccTool ? __('pages/bd.cover_alt', ['title' => $first->title]) : null,
                'breadcrumb' => [[$title, $canonical]],
                'schema' => [StructuredData::collectionPage($canonical, $title, $comics->count(), $comics->map(static fn (CccTool $tool): array => [
                    'url' => route('bd.show', ['key' => $tool->key]),
                    'name' => (string) $tool->title,
                ])->values()->all())],
            ], $user instanceof User ? $user : null),
        ]);
    }
}
