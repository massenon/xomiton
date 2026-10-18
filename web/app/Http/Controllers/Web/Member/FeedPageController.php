<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Member;

use App\Application\Feed\FeedComposerOptions;
use App\Application\Feed\Queries\FeedHighlights;
use App\Application\Feed\Queries\FeedQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\FeedPageRequest;
use App\Http\Resources\Feed\CommentResource;
use App\Http\Resources\Feed\PostResource;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Écrans Vue du fil (P7-03 à P7-06, prototype fil.php, post-details.php) : pages Inertia `Feed/Index` (`/fil`),
 * `Feed/Show` (`/fil/{ulid}`) et `Feed/Saved` (`/fil/enregistres`) dans le gabarit membre. Première page servie dans les
 * props, sous la même forme que l'API (`{ data, meta: { next_cursor, per_page } }`, PostResource / CommentResource,
 * docs/API.md §8) : la suite (« Charger plus ») et les actions passent par `/api/v1` (useApi). Props évaluées à la
 * demande (closures) : un changement de filtre ne recharge que `feed` et `tag` (rechargement partiel Inertia).
 * `report_reasons` : motifs de la modale de signalement (FeedComposerOptions::reportReasons(), fournis aux cartes).
 * Publication invisible (visibilité, masquée, groupe privé, blocage, supprimée) : 404, comme l'API (Q72).
 */
final class FeedPageController extends Controller
{
    public function __construct(
        private readonly FeedQuery $feed,
        private readonly FeedHighlights $highlights,
        private readonly FeedComposerOptions $composer,
    ) {}

    public function index(FeedPageRequest $request): Response
    {
        $viewer = $request->viewer();
        $tag = $request->tag();
        $title = (string) __('menus.member_pages.feed');

        return Inertia::render('Feed/Index', [
            'title' => $title,
            'tag' => $tag?->value,
            'feed' => fn (): array => $this->posts($viewer, fn (): Builder => $this->feed->feed($viewer, $tag)),
            'featured' => fn (): array => $this->highlights->thematiques(),
            'events' => fn (): array => $this->highlights->events(),
            'composer' => fn (): array => $this->composer->for($viewer),
            'report_reasons' => fn (): array => $this->composer->reportReasons(),
        ])->withViewData(['title' => $title]);
    }

    public function saved(FeedPageRequest $request): Response
    {
        $viewer = $request->viewer();
        $title = (string) __('feed.post.saved_title');

        return Inertia::render('Feed/Saved', [
            'title' => $title,
            'saved' => fn (): array => $this->posts($viewer, fn (): Builder => $this->feed->saved($viewer)),
            'events' => fn (): array => $this->highlights->events(),
            'report_reasons' => fn (): array => $this->composer->reportReasons(),
        ])->withViewData(['title' => $title]);
    }

    public function show(FeedPageRequest $request, Post $post): Response
    {
        $viewer = $request->viewer();

        if (! $viewer->can('view', $post)) {
            throw new NotFoundHttpException;
        }

        $title = (string) __('menus.member_pages.post');

        return Inertia::render('Feed/Show', [
            'title' => $title,
            'post' => fn (): array => PostResource::make($this->feed->loadViewerState($post, $viewer))->resolve($request),
            'comments' => fn (): array => $this->page(
                $this->feed->comments($post, $viewer),
                (int) config('xomiton.feed.comments_per_page', 20),
                CommentResource::class,
                ['comments_count' => (int) $post->comments_count],
            ),
            'events' => fn (): array => $this->highlights->events(),
            'report_reasons' => fn (): array => $this->composer->reportReasons(),
        ])->withViewData(['title' => $title]);
    }

    /**
     * Première page d'une liste de publications ; vide sans accès au réseau (`social.access`).
     *
     * @param  \Closure(): Builder<Post>  $query
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    private function posts(User $viewer, \Closure $query): array
    {
        $perPage = (int) config('xomiton.feed.per_page', 20);

        if (! $viewer->can('viewAny', Post::class)) {
            return ['data' => [], 'meta' => ['next_cursor' => null, 'per_page' => $perPage]];
        }

        return $this->page($this->feed->withViewerState($query(), $viewer), $perPage, PostResource::class);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  class-string<JsonResource>  $resource
     * @param  array<string, mixed>  $meta
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    private function page(Builder $query, int $perPage, string $resource, array $meta = []): array
    {
        $paginator = $query->cursorPaginate($perPage);

        /** @var list<array<string, mixed>> $items */
        $items = $resource::collection(collect($paginator->items()))->resolve(request());

        return [
            'data' => $items,
            'meta' => ['next_cursor' => $paginator->nextCursor()?->encode(), 'per_page' => $paginator->perPage()] + $meta,
        ];
    }
}
