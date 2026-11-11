<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Events;

use App\Application\Community\Events\CreateEvent;
use App\Application\Community\Events\EventDirectory;
use App\Application\Community\Events\EventLinks;
use App\Application\Community\Events\RespondToEvent;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Events\ListEventsRequest;
use App\Http\Requests\Api\Events\RsvpEventRequest;
use App\Http\Requests\Api\Events\StoreEventRequest;
use App\Http\Resources\Events\EventResource;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * /api/v1/events (P8-04, docs/API.md §15 ; simulateur.html l.1162) : liste (à venir, mes participations, passés ;
 * catégorie, recherche, pagination par page `xomiton.events.per_page`), création (201 + Location, la personne devient
 * l'organisatrice), fiche — **sans `address` ni `online_url` pour qui n'a pas répondu « Je participe »** — et réponse
 * « Je participe » / annuler (e-mail confirmé pour participer, annuler reste possible). Événement invisible (groupe
 * privé, brouillon, masqué, supprimé, organisateur·rice bloqué·e ou qui bloque) : 404, jamais 403. Toutes les réponses
 * sont `no-store` (Q74, Q77) : elles peuvent porter l'adresse exacte ou le lien déchiffrés, jamais gardés dans un cache
 * HTTP du navigateur ou de l'appareil (le middleware `conditional` laisse alors la réponse sans ETag).
 */
final class EventController extends ApiController
{
    public function __construct(private readonly EventDirectory $events) {}

    public function index(ListEventsRequest $request): JsonResponse
    {
        $viewer = $this->viewer($request);
        $paginator = $this->events->paginate($viewer, $request->filter(), $request->category(), $request->term(), $this->perPage($request, (int) config('xomiton.events.per_page', 6)));
        $paginator->withQueryString();

        return $this->data(EventResource::list($paginator->items(), $this->events->rsvps($viewer, $paginator->items()), $request), 200, [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'filter' => $request->filter(),
        ], [
            'first' => $paginator->url(1),
            'last' => $paginator->url($paginator->lastPage()),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ])->withHeaders(self::NO_STORE);
    }

    public function store(StoreEventRequest $request, CreateEvent $create): JsonResponse
    {
        $event = $create($this->viewer($request), $request->toDraft())->load(['category', 'group']);

        return $this->created(EventResource::make($event)->withRsvp(null), EventLinks::show($event))->withHeaders(self::NO_STORE);
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        $viewer = $this->viewer($request);
        $this->visible($viewer, $event)->loadMissing(['category', 'group']);

        return $this->respond(EventResource::make($event)->withRsvp($this->events->rsvp($viewer, $event)), headers: self::NO_STORE);
    }

    public function rsvp(RsvpEventRequest $request, RespondToEvent $respond, Event $event): JsonResponse
    {
        $viewer = $this->viewer($request);
        $this->visible($viewer, $event);

        if (! $viewer->can('rsvp', $event)) {
            throw new NotFoundHttpException;
        }

        $rsvp = $respond($viewer, $event, $request->going(), $request->reminder());

        return $this->respond(EventResource::make($event->loadMissing(['category', 'group']))->withRsvp($rsvp), headers: self::NO_STORE);
    }

    private function viewer(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * @throws NotFoundHttpException
     */
    private function visible(User $viewer, Event $event): Event
    {
        if (! $viewer->can('view', $event)) {
            throw new NotFoundHttpException;
        }

        return $event;
    }
}
