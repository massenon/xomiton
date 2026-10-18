<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Member;

use App\Application\Community\Pages\PageDirectory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\CommunityPageRequest;
use App\Http\Resources\Community\PageResource;
use App\Models\Page;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Écrans Vue des pages d'organisations (P8-03, prototype pages.php, create-page.php) : `Community/Pages` (`/pages` :
 * onglets Toutes · Abonnements · Mes pages, catégories, recherche, « Charger plus », Suivre la page),
 * `Community/PageCreate` (`/pages/creer` : nom, catégorie, description, site, mention de la vérification) et
 * `Community/PageShow` (`/pages/{ulid}` : couverture, badges, abonné·es, Suivre, À propos, site). Une page non approuvée
 * n'est visible que de sa propriétaire (bandeau « en attente ») et de la modération ; 404 pour les autres (P12-05).
 */
final class PagesPageController extends Controller
{
    public function __construct(private readonly PageDirectory $pages) {}

    public function index(CommunityPageRequest $request): Response
    {
        $viewer = $request->viewer();
        $tab = $request->choice('tab', PageDirectory::TABS, PageDirectory::TAB_ALL);
        $category = $request->term('categorie');
        $search = $request->term();
        $title = (string) __('community.pages.title');

        return Inertia::render('Community/Pages', [
            'title' => $title,
            'tab' => $tab,
            'category' => $category,
            'q' => $search,
            'categories' => fn (): array => $this->pages->categories(),
            'pages' => function () use ($request, $viewer, $tab, $category, $search): array {
                // Sans accès au réseau (`social.access`, comme ListPagesRequest) : liste vide, comme le fil.
                if (! $viewer->can('viewAny', Page::class)) {
                    return ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0]];
                }

                $paginator = $this->pages->paginate($viewer, $tab, $category, $search, (int) config('xomiton.community.pages_per_page', 12));
                $followed = $this->pages->followed($viewer, $paginator->items());

                return [
                    'data' => array_map(
                        static fn (Page $page): array => PageResource::make($page)->withFollowing(isset($followed[(int) $page->getKey()]))->resolve($request),
                        $paginator->items(),
                    ),
                    'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
                ];
            },
            'can_create' => $viewer->can('create', Page::class),
        ])->withViewData(['title' => $title]);
    }

    public function create(CommunityPageRequest $request): Response
    {
        $title = (string) __('community.pages.create.title');

        abort_unless($request->viewer()->can('create', Page::class), 403);

        return Inertia::render('Community/PageCreate', [
            'title' => $title,
            'categories' => PageDirectory::choices(),
            'limits' => [
                'name_min' => (int) config('xomiton.community.page_name_min', 3),
                'name_max' => (int) config('xomiton.community.page_name_max', 80),
                'description_max' => (int) config('xomiton.community.page_description_max', 400),
            ],
        ])->withViewData(['title' => $title]);
    }

    public function show(CommunityPageRequest $request, Page $page): Response
    {
        if (! $request->viewer()->can('view', $page)) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('Community/PageShow', [
            'title' => (string) $page->name,
            'organization' => fn (): array => PageResource::make($page)->resolve($request),
        ])->withViewData(['title' => (string) $page->name]);
    }
}
