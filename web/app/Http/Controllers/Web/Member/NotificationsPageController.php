<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Member;

use App\Application\Notifications\NotificationInbox;
use App\Application\Notifications\NotificationTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\NotificationsPageRequest;
use App\Http\Resources\NotificationResource;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Écran Vue « Notifications » (P8-10, prototype notifications.php l.8-9) : page Inertia `Member/Notifications` dans le
 * gabarit membre — en-tête avec le nombre de non lues, « Tout marquer comme lu », filtres Toutes · Invitations ·
 * Événements · Échanges · Système avec leur badge de non lues (`?filtre=`), liste avec « Marquer comme lu » par
 * notification. Première page servie dans les props sous la forme de l'API (P6-07, NotificationResource : texte
 * neutralisé si les aperçus sont masqués) ; la suite (« Charger plus ») et les gestes passent par `/api/v1/notifications`
 * (`?tab=`). L'état lu est en base (`notifications.read_at`), plus en session comme le prototype.
 */
final class NotificationsPageController extends Controller
{
    /** Notifications par page (première page dans les props, suite par l'API avec le même `per_page`). */
    public const PER_PAGE = 20;

    public function __construct(private readonly NotificationInbox $inbox) {}

    public function __invoke(NotificationsPageRequest $request): Response
    {
        $user = $request->viewer();
        $user->loadMissing('settings');
        $tab = $request->tab();
        $title = (string) __('notifications.page.title');
        $perPage = self::PER_PAGE;

        return Inertia::render('Member/Notifications', [
            'title' => $title,
            'tab' => $tab->value,
            'tabs' => array_map(static fn (NotificationTab $item): array => [
                'key' => $item->value,
                'param' => NotificationsPageRequest::paramOf($item),
            ], NotificationTab::ordered()),
            'counts' => fn (): array => $this->inbox->counts($user)['tabs'],
            'notifications' => function () use ($request, $user, $tab, $perPage): array {
                $paginator = $this->inbox->query($user, NotificationInbox::FILTER_ALL, null, $tab === NotificationTab::All ? null : $tab)
                    ->paginate($perPage);

                return [
                    'data' => NotificationResource::collection(collect($paginator->items()))->resolve($request),
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'total' => $paginator->total(),
                    ],
                ];
            },
            'per_page' => $perPage,
        ])->withViewData(['title' => $title]);
    }
}
