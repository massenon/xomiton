<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Legal;

use App\Application\Legal\LegalTexts;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Pages légales publiques et indexables (vous) : `/politique-de-confidentialite`, `/conditions-utilisation`,
 * `/mentions-legales`, `/cookies`. Une seule vue (`public.legal.page`), un texte par page dans lang/fr/pages/legal.php,
 * jetons remplacés par la configuration (App\Application\Legal\LegalTexts). La route porte la clé de la page
 * (`->defaults('page', …)`). Accessibles sans connexion (exigence Google Play pour la politique de confidentialité).
 */
final class LegalPageController extends Controller
{
    /** @var list<string> */
    public const PAGES = ['privacy', 'terms', 'notice', 'cookies'];

    /** Nom de route de chaque page. */
    public const ROUTES = [
        'privacy' => 'legal.privacy',
        'terms' => 'legal.terms',
        'notice' => 'legal.notice',
        'cookies' => 'legal.cookies',
    ];

    public function __invoke(Request $request): View
    {
        $page = (string) $request->route('page');
        abort_unless(in_array($page, self::PAGES, true), 404);

        /** @var array<string, mixed> $content */
        $content = LegalTexts::resolve((array) __('pages/legal.pages.'.$page));
        $updatedAt = (string) config('xomiton.content.legal_updated_at', '2026-10-29');

        return view('public.legal.page', [
            'page' => $page,
            'content' => $content,
            'updatedAt' => $updatedAt,
            'seo' => [
                'title' => __('seo.pages.legal.'.$page.'.title'),
                'description' => __('seo.pages.legal.'.$page.'.description'),
                'modified' => $updatedAt,
                'breadcrumb' => [[(string) $content['title'], route(self::ROUTES[$page])]],
            ],
        ]);
    }
}
