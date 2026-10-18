<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Notifications\NotificationInbox;
use App\Http\Requests\Api\Notifications\ListNotificationsRequest;
use App\Http\Resources\NotificationResource;
use App\Models\DatabaseNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * /api/v1/notifications (P6-07, docs/API.md §7) : cloche de la personne connectée — liste paginée par page
 * (`?filter=all|unread|read`, `?category=`), compteurs par filtre et par catégorie (`unread-count`, polling 30 s, ETag),
 * lecture d'une notification, tout marquer comme lu, suppression. Une notification d'un autre compte : 404.
 */
final class NotificationController extends ApiController
{
    public function __construct(private readonly NotificationInbox $inbox) {}

    public function index(ListNotificationsRequest $request): JsonResponse
    {
        $user = $this->user($request);
        $user->loadMissing('settings');

        $paginator = $this->inbox->query($user, $request->filter(), $request->category())
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->paginated($paginator, NotificationResource::class, [
            'filter' => $request->filter(),
            'category' => $request->category(),
            'unread' => $this->inbox->counts($user)['unread'],
        ]);
    }

    public function counts(Request $request): JsonResponse
    {
        return $this->data($this->inbox->counts($this->user($request)));
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $user = $this->user($request);
        $row = $this->owned($user, $notification);
        Gate::forUser($user)->authorize('update', $row);
        $user->loadMissing('settings');

        return $this->respond(new NotificationResource($this->inbox->markRead($row)), meta: [
            'unread' => $this->inbox->counts($user)['unread'],
        ]);
    }

    public function readAll(ListNotificationsRequest $request): JsonResponse
    {
        $user = $this->user($request);
        $marked = $this->inbox->markAllRead($user, $request->category());

        return $this->data(['marked' => $marked, 'unread' => $this->inbox->counts($user)['unread']]);
    }

    public function destroy(Request $request, string $notification): Response
    {
        $user = $this->user($request);
        $row = $this->owned($user, $notification);
        Gate::forUser($user)->authorize('delete', $row);

        $this->inbox->delete($row);

        return $this->noContent();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function owned(User $user, string $id): DatabaseNotification
    {
        return $this->inbox->find($user, $id) ?? throw new NotFoundHttpException;
    }
}
